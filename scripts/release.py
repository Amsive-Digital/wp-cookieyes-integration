"""Validate/prepare versions and build reproducible, allowlisted installation ZIPs."""
import argparse
import hashlib
from pathlib import Path
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SLUG = 'cookieyes-integration'
SEMVER = r'(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)'

def require(condition, message='Release validation failed'):
    if not condition:
        raise ValueError(message)

def version():
    return re.search(r'^ \* Version: (' + SEMVER + r')$', (ROOT / (SLUG + '.php')).read_text(), re.M).group(1)

def check(expected=None):
    value = version()
    require(expected is None or value == expected, 'Requested version differs from source')
    source = (ROOT / (SLUG + '.php')).read_text()
    require("define( 'COOKIEYES_INTEGRATION_VERSION', '" + value + "' );" in source, 'Version constant mismatch')
    require('Stable tag: ' + value + '\n' in (ROOT / 'readme.txt').read_text(), 'Stable tag mismatch')
    require('Version **' + value + '**' in (ROOT / 'README.md').read_text(), 'README version mismatch')
    require(notes(value).strip(), 'Missing release notes')
    return value

def notes(value):
    text = (ROOT / 'CHANGELOG.md').read_text()
    match = re.search(r'^## ' + re.escape(value) + r'\n(.*?)(?=^## |\Z)', text, re.M | re.S)
    require(match, 'Missing version section in CHANGELOG.md')
    return match.group(1).strip()

def prepare(value):
    require(re.fullmatch(SEMVER, value), 'Use stable X.Y.Z version')
    old = check()
    require(tuple(map(int, value.split('.'))) > tuple(map(int, old.split('.'))), 'Version must increase')
    require(notes(value), 'Write the new changelog section before preparing the version')
    p = ROOT / (SLUG + '.php')
    text = p.read_text().replace('Version: ' + old + '\n', 'Version: ' + value + '\n')
    text = text.replace("'COOKIEYES_INTEGRATION_VERSION', '" + old + "'", "'COOKIEYES_INTEGRATION_VERSION', '" + value + "'")
    p.write_text(text)
    for filename, before, after in [('README.md', 'Version **' + old + '**', 'Version **' + value + '**'), ('readme.txt', 'Stable tag: ' + old + '\n', 'Stable tag: ' + value + '\n')]:
        p = ROOT / filename
        p.write_text(p.read_text().replace(before, after, 1))
    check(value)

def build(destination, expected=None):
    value = check(expected)
    destination = Path(destination)
    destination.mkdir(parents=True, exist_ok=True)
    output = destination / (SLUG + '-v' + value + '.zip')
    files = [ROOT / name for name in [SLUG + '.php', 'uninstall.php', 'README.md', 'readme.txt', 'CHANGELOG.md', 'LICENSE']]
    files += sorted((ROOT / 'includes').rglob('*.php'))
    for path in files:
        require(path.is_file() and (not path.is_symlink()), 'Missing or symlinked package source')
    with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(files):
            info = zipfile.ZipInfo(SLUG + '/' + str(path.relative_to(ROOT)), date_time=(2020, 1, 1, 0, 0, 0))
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, path.read_bytes())
    with zipfile.ZipFile(output) as archive:
        require(archive.testzip() is None, 'Release validation failed')
        require(set(archive.namelist()) == {SLUG + '/' + str(p.relative_to(ROOT)) for p in files}, 'Release validation failed')
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    output.with_suffix('.zip.sha256').write_text(digest + '  ' + output.name + '\n')
    return output

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=['check', 'prepare', 'build', 'notes'])
    parser.add_argument('--version')
    parser.add_argument('--output', default=str(ROOT / 'dist'))
    args = parser.parse_args()
    if args.operation == 'prepare':
        require(args.version, '--version required')
        prepare(args.version)
    elif args.operation == 'build':
        print(build(args.output, args.version))
    elif args.operation == 'notes':
        print(notes(check(args.version)))
    else:
        print(check(args.version))

if __name__ == '__main__':
    main()
