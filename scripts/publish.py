"""Publish the checked main commit; exact reruns are safe, mismatched releases are refused."""
import hashlib
import json
import os
import re
from pathlib import Path
import subprocess
import tempfile
from release import ROOT, build, check, notes, require

REPO = 'Amsive-Digital/wp-cookieyes-integration'

def gh(*args, allow_missing=False):
    result = subprocess.run(['gh', *args], cwd=ROOT, text=True, capture_output=True)
    if result.returncode:
        if allow_missing and ('HTTP 404' in result.stderr or 'release not found' in result.stderr.lower()):
            return None
        raise RuntimeError(result.stderr)
    return result.stdout

def api(path, allow_missing=False, method=None, payload=None):
    args = ['api', 'repos/' + REPO + '/' + path]
    if method:
        args += ['--method', method]
    if payload is not None:
        with tempfile.TemporaryDirectory(prefix='cookieyes-api-') as temporary:
            body = Path(temporary) / 'body.json'
            body.write_text(json.dumps(payload), encoding='utf-8')
            raw = gh(*args, '--input', str(body), allow_missing=allow_missing)
    else:
        raw = gh(*args, allow_missing=allow_missing)
    return None if raw is None else json.loads(raw)

def all_releases():
    releases = []
    page = 1
    while True:
        batch = api('releases?per_page=100&page=' + str(page))
        releases += batch
        if len(batch) < 100:
            return releases
        page += 1

def main():
    require(os.environ.get('GITHUB_REPOSITORY') == REPO, 'Run in the expected repository')
    require(os.environ.get('GITHUB_REF') == 'refs/heads/main', 'Publish only from main')
    commit = os.environ['GITHUB_SHA']
    require(subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip() == commit, 'Release validation failed')
    require(api('commits/main')['sha'] == commit, 'Dispatch commit is no longer main tip; dispatch again')
    require(not subprocess.check_output(['git', 'status', '--porcelain'], cwd=ROOT, text=True).strip(), 'Dirty release source')
    value = check(os.environ['RELEASE_VERSION'])
    tag = 'v' + value
    existing_tag = api('git/ref/tags/' + tag, allow_missing=True)
    if existing_tag:
        obj = existing_tag['object']
        while obj['type'] == 'tag':
            obj = api('git/tags/' + obj['sha'])['object']
        require(obj['type'] == 'commit' and obj['sha'] == commit, 'Tag points at a different commit')
    releases = all_releases()
    matches = [item for item in releases if item['tag_name'] == tag]
    require(len(matches) <= 1, 'Multiple releases use the same tag')
    release = matches[0] if matches else None
    if release:
        require(existing_tag or (release['draft'] and release['target_commitish'] == commit), 'Existing release must have a verified tag or exact draft target')
        require(not release['prerelease'], 'Refusing to mutate a prerelease')
    if release is None or release['draft']:
        candidate = tuple(map(int, value.split('.')))
        for item in releases:
            match = re.fullmatch(r'v(\d+)\.(\d+)\.(\d+)', item['tag_name'])
            if not item['draft'] and not item['prerelease'] and match:
                require(candidate > tuple(map(int, match.groups())), 'A newer stable release already exists')
    with tempfile.TemporaryDirectory(prefix='cookieyes-release-') as temporary:
        directory = Path(temporary)
        package = build(directory, value)
        checksum = package.with_suffix('.zip.sha256')
        assets = [package, checksum]
        if release is None:
            # Draft first: the updater never sees partially uploaded packages.
            release = api('releases', method='POST', payload={
                'tag_name': tag, 'target_commitish': commit, 'draft': True, 'prerelease': False,
                'name': 'CookieYes Integration ' + tag, 'body': notes(value),
            })
        release_path = 'releases/' + str(release['id'])
        remote = {asset['name']: asset for asset in release['assets']}
        for asset in assets:
            if asset.name in remote:
                digest = 'sha256:' + hashlib.sha256(asset.read_bytes()).hexdigest()
                require(remote[asset.name].get('digest') == digest, 'Existing asset differs; never overwrite a published version')
            else:
                require(release['draft'], 'Published release is missing an asset; publish a new version')
                gh('release', 'upload', tag, str(asset), '--repo', REPO)
        verified = api(release_path)
        by_name = {asset['name']: asset for asset in verified['assets']}
        for asset in assets:
            require(by_name[asset.name]['digest'] == 'sha256:' + hashlib.sha256(asset.read_bytes()).hexdigest(), 'Uploaded digest mismatch')
        if verified['draft']:
            api(release_path, method='PATCH', payload={'draft': False, 'make_latest': 'true'})
        final = api(release_path)
        require(not final['draft'] and (not final['prerelease']), 'Release validation failed')
        final_tag = api('git/ref/tags/' + tag)['object']
        while final_tag['type'] == 'tag':
            final_tag = api('git/tags/' + final_tag['sha'])['object']
        require(final_tag['type'] == 'commit' and final_tag['sha'] == commit, 'Published tag mismatch')
        print(final['html_url'])

if __name__ == '__main__':
    main()
