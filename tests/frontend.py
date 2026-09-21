#!/usr/bin/env python3
"""Check real front-end output after the WordPress integration upgrade."""
import os
import pathlib
import re
import subprocess
import time
import urllib.request

root = pathlib.Path(os.environ['RUNNER_TEMP']) / 'wordpress'
base = 'http://127.0.0.1:8080/'
marker = '<meta name="at-head-tag-test" content="100%20">'
log_path = pathlib.Path(os.environ['RUNNER_TEMP']) / 'at-head-tag-http.log'
with log_path.open('w') as log:
    server = subprocess.Popen(['php', '-S', '127.0.0.1:8080', '-t', str(root)], stdout=log, stderr=log)
    try:
        def fetch():
            with urllib.request.urlopen(base, timeout=15) as response:
                return response.read().decode('utf-8')
        for attempt in range(30):
            try:
                html = fetch()
                break
            except (OSError, TimeoutError):
                if server.poll() is not None or attempt == 29:
                    raise
                time.sleep(0.2)
        head = re.search(r'<head\b[^>]*>(.*?)</head>', html, re.I | re.S)
        assert head, html[:1000]
        assert marker in head.group(1)
        assert html.count('<!-- at-head-tag START -->') == 1
        assert html.count('<!-- at-head-tag END -->') == 1
        print('PASS: Real theme output contains the exact head snippet once inside <head>.')
        subprocess.run(['wp', 'option', 'update', 'at_head_tag_enabled', '0', f'--path={root}'], check=True, stdout=subprocess.DEVNULL)
        disabled = fetch()
        assert marker not in disabled
        print('PASS: Disabling output removes the head snippet without deleting it.')
        subprocess.run(['wp', 'option', 'update', 'at_head_tag_enabled', '1', f'--path={root}'], check=True, stdout=subprocess.DEVNULL)
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait()
