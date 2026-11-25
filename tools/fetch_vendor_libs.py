#!/usr/bin/env python3
"""Download the studio and auth page libraries into public/assets/vendor/.

Run once from the repository root:   python3 -I tools/fetch_vendor_libs.py
Each file is checked against its published size and SHA-256 (jsDelivr) before it
is saved.
"""
import base64, hashlib, pathlib, sys, urllib.request

LIBS = [  # (url, saved as, size in bytes, sha256 base64 from data.jsdelivr.com)
    ('https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js', 'gsap.min.js', 72214,
     'KAM+RJox68w5blvosTtjFSvwMJQoj7WGcDQyGSe84Ic='),
    ('https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js', 'three.min.js', 669884,
     'FwxnifQyF8lrMXD0tC+v4TXef3zUhJekIY+XV+4dSfo='),
    ('https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js', 'pdf.min.js', 320004,
     'W1eZ5vjGgGYyB6xbQu4U7tKkBvp69I9QwVTwwLFWaUY='),
    ('https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js', 'pdf.worker.min.js', 1087212,
     '/qvfMJdw7SS7oxpUZ4Ns3Iz2OccFryfVK1hbBBu4Uns='),
]

def sha(data):
    return base64.b64encode(hashlib.sha256(data).digest()).decode()

def main():
    root = pathlib.Path.cwd()
    if not (root / 'public' / 'studio.php').is_file():
        sys.exit('Run this from the repository root (the folder that contains public/).')
    out = root / 'public' / 'assets' / 'vendor'
    out.mkdir(parents=True, exist_ok=True)
    for url, name, size, digest in LIBS:
        dest = out / name
        if dest.is_file() and dest.stat().st_size == size and sha(dest.read_bytes()) == digest:
            print('OK (already present):', name)
            continue
        print('Downloading', url)
        try:
            with urllib.request.urlopen(url, timeout=60) as r:
                data = r.read()
        except OSError as e:
            sys.exit(f'STOP: could not download {name} ({e}). Check your internet connection and run this again.')
        if len(data) != size or sha(data) != digest:
            sys.exit(f'STOP: {name} did not match the expected size/checksum '
                     f'(got {len(data)} bytes, sha256 {sha(data)}). Nothing was saved for this file.')
        dest.write_bytes(data)
        print('Saved', dest.relative_to(root), f'({size} bytes, checksum verified)')
    print('Done. Commit public/assets/vendor/*.js')

if __name__ == '__main__':
    main()
