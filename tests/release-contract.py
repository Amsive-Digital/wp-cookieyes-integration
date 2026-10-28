"""Packaging/version and immutable publisher tests; no network or real publication."""
import copy
import hashlib
import importlib.util
import os
from pathlib import Path
import shutil
import sys
import tempfile
import zipfile
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.dont_write_bytecode = True
sys.path.insert(0, str(ROOT / 'scripts'))
import release
import publish

checks = 0
def verify(ok, message):
    global checks
    checks += 1
    assert ok, message

with tempfile.TemporaryDirectory(prefix='cookieyes-tests-') as temp:
    directory = Path(temp)
    first = release.build(directory / 'first')
    second = release.build(directory / 'second')
    verify(first.read_bytes() == second.read_bytes(), 'Package must be reproducible')
    with zipfile.ZipFile(first) as archive:
        verify(all(name.startswith('cookieyes-integration/') for name in archive.namelist()), 'Wrong ZIP root')
        verify(not any(part in name for name in archive.namelist() for part in ['.git', '/tests/', '/scripts/', 'AGENTS.md', 'RELEASE.md']), 'Development files leaked')
        verify('cookieyes-integration/cookieyes-integration.php' in archive.namelist(), 'Missing bootstrap')
    verify(first.with_suffix('.zip.sha256').read_text().split()[0] == hashlib.sha256(first.read_bytes()).hexdigest(), 'Checksum mismatch')
    clone = directory / 'source'
    shutil.copytree(ROOT, clone, ignore=shutil.ignore_patterns('.git', 'dist', '__pycache__'))
    with patch.object(release, 'ROOT', clone):
        old = release.check()
        newer = '.'.join(old.split('.')[:2] + [str(int(old.split('.')[2]) + 1)])
        p = clone / 'CHANGELOG.md'
        p.write_text('# Changelog\n\n## ' + newer + '\n\n- Fixture release.\n\n' + p.read_text().split('\n', 1)[1])
        release.prepare(newer)
        verify(release.check(newer) == newer, 'Preparation did not synchronize versions')
        try:
            release.prepare(old)
            raise RuntimeError('Decreasing version was accepted')
        except (AssertionError, ValueError):
            verify(True, 'Decreasing version rejected')

commit = 'a' * 40
tag = 'v' + release.check()
state = {'tag': None, 'release': None}
def fake_api(path, allow_missing=False, method=None, payload=None):
    if path == 'commits/main': return {'sha': commit}
    if path.startswith('git/ref/tags/'): return copy.deepcopy(state['tag'])
    if path.startswith('releases?'):
        return [] if state['release'] is None else [copy.deepcopy(state['release'])]
    if path == 'releases' and method == 'POST':
        state['release'] = dict(payload, id=1, assets=[], html_url='https://github.com/' + publish.REPO + '/releases/tag/' + tag)
        return copy.deepcopy(state['release'])
    if path == 'releases/1':
        if method == 'PATCH':
            state['release']['draft'] = payload['draft']
            state['tag'] = {'object': {'type': 'commit', 'sha': commit}}
        return copy.deepcopy(state['release'])
    # A draft MUST NOT be fetched via releases/tags; GitHub documents this as published-only.
    if path.startswith('releases/tags/'):
        raise RuntimeError('Forbidden draft-by-tag API path')
    raise RuntimeError(path)

def fake_gh(*args, **kwargs):
    command = args[:2]
    if command == ('release', 'create'):
        state['release'] = {'draft': True, 'prerelease': False, 'target_commitish': commit, 'assets': [], 'html_url': 'https://github.com/' + publish.REPO + '/releases/tag/' + tag}
    elif command == ('release', 'upload'):
        asset = Path(args[3])
        state['release']['assets'].append({'name': asset.name, 'digest': 'sha256:' + hashlib.sha256(asset.read_bytes()).hexdigest()})
    elif command == ('release', 'edit'):
        state['release']['draft'] = False
        state['tag'] = {'object': {'type': 'commit', 'sha': commit}}
    else:
        raise RuntimeError(args)
    return ''

def fake_git(args, **kwargs):
    return commit + '\n' if args[-1] == 'HEAD' else ''

environment = {'GITHUB_REPOSITORY': publish.REPO, 'GITHUB_REF': 'refs/heads/main', 'GITHUB_SHA': commit, 'RELEASE_VERSION': release.check()}
with patch.dict(os.environ, environment), patch.object(publish, 'api', fake_api), patch.object(publish, 'gh', fake_gh), patch.object(publish.subprocess, 'check_output', fake_git):
    publish.main()
    verify(not state['release']['draft'] and len(state['release']['assets']) == 2, 'New publication incomplete')
    published = copy.deepcopy(state)
    publish.main()
    verify(state == published, 'Exact rerun mutated release')
    for mode in ['tag', 'digest', 'missing', 'prerelease']:
        state = copy.deepcopy(published)
        if mode == 'tag': state['tag']['object']['sha'] = 'b' * 40
        if mode == 'digest': state['release']['assets'][0]['digest'] = 'sha256:bad'
        if mode == 'missing': state['release']['assets'] = []
        if mode == 'prerelease': state['release']['prerelease'] = True
        try:
            publish.main()
            raise RuntimeError('Unsafe ' + mode + ' was accepted')
        except (AssertionError, ValueError):
            verify(True, mode + ' rejected')
    state = {'tag': None, 'release': copy.deepcopy(published['release'])}
    state['release'].update(draft=True, assets=[])
    publish.main()
    verify(not state['release']['draft'] and state['tag']['object']['sha'] == commit, 'Draft recovery failed')
    with patch.dict(os.environ, {'GITHUB_REF': 'refs/heads/other'}):
        try:
            publish.main()
            raise RuntimeError('Publishing from another branch was accepted')
        except (AssertionError, ValueError):
            verify(True, 'Non-main rejected')
print(f'PASS: {checks} release contract checks (no network or publication).')
