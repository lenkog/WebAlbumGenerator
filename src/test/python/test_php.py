import argparse
import json
import os
import pty
import subprocess
import sys
import unittest
import urllib.parse
import urllib.request

MEDIA_EXT = {
    '.jpg': 'image/jpeg',
    '.png': 'image/png',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.webm': 'video/webm',
    '.mp4': 'video/mp4',
    '.mpeg4': 'video/mp4',
    '.m4v': 'video/mp4',
}

testFolder = None


def call(path, decode=True, method='GET'):
    request = urllib.request.Request(
        'http://localhost:8000/' + os.path.basename(testFolder) + '/wag.php' + urllib.parse.quote(path, safe='/'), method=method)
    try:
        response = urllib.request.urlopen(request)
    except urllib.error.HTTPError as e:
        return (e.code, '', '')
    data = response.read()
    if decode:
        data = data.decode('utf-8')
    return (response.status, response.getheader('Content-Type', ''), data)


class TestPHP(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        master, slave = pty.openpty()
        cls.server = subprocess.Popen(
            ['php', '-S', 'localhost:8000'], cwd=os.path.dirname(testFolder), stdout=slave, stderr=slave, close_fds=True)
        with os.fdopen(master) as stdout:
            line = stdout.readline()
            while 'http://localhost:8000' not in line:
                line = stdout.readline()

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()

    def test_invalid(self):
        self.assertEqual(call('/invalid')[0], 404)

    def test_html(self):
        base = os.path.basename(testFolder)

        res = call('')
        self.assertEqual(res[0], 200)
        self.assertTrue('text/html' in res[1])
        self.assertEqual(res[2],
                         '<html><head><script>const API_ENDPOINT = "\\/' + base + '\\/wag.php\\/api";</script><script src="/' + base + '/wag.php/app.js"></script></head><body class="wagBody"><div id="wag"></div></body></html>')
        res = call('/')
        self.assertEqual(res[0], 200)
        self.assertTrue('text/html' in res[1])
        self.assertEqual(res[2],
                         '<html><head><script>const API_ENDPOINT = "\\/' + base + '\\/wag.php\\/api";</script><script src="/' + base + '/wag.php/app.js"></script></head><body class="wagBody"><div id="wag"></div></body></html>')

    def test_js(self):
        res = call('/app.js')
        self.assertEqual(res[0], 200)
        self.assertTrue('application/javascript' in res[1])

    def test_assets(self):
        res = call('/api/assets/default-thumbnail', decode=False)
        self.assertEqual(res[0], 200)
        self.assertTrue('image/gif' in res[1])
        res = call('/api/assets/overlay-album', decode=False)
        self.assertEqual(res[0], 200)
        self.assertTrue('image/gif' in res[1])
        res = call('/api/assets/overlay-video', decode=False)
        self.assertEqual(res[0], 200)
        self.assertTrue('image/gif' in res[1])
        res = call('/api/assets/subalbum', decode=False)
        self.assertEqual(res[0], 200)
        self.assertTrue('image/gif' in res[1])

        res = call('/api/assets/default-thumbnail', decode=False, method='PUT')
        self.assertEqual(res[0], 404)
        res = call('/api/assets/default-thumbnail',
                   decode=False, method='POST')
        self.assertEqual(res[0], 404)
        res = call('/api/assets/default-thumbnail',
                   decode=False, method='DELETE')
        self.assertEqual(res[0], 404)

        res = call('/api/assets', decode=False)
        self.assertEqual(res[0], 404)
        res = call('/api/assets/', decode=False)
        self.assertEqual(res[0], 404)
        res = call('/api/assets/invalid', decode=False)
        self.assertEqual(res[0], 404)

    def rescurse_albums(self, folder):
        expected = os.listdir(os.path.join(testFolder, folder))
        res = call('/api/albums/' + folder)
        self.assertEqual(res[0], 200)
        self.assertTrue('application/json' in res[1])
        actual = json.loads(res[2])
        self.assertEqual(
            actual['mediaURL'], '/' + os.path.basename(testFolder) + '/wag.php/api/media/')
        expectedCount = 0
        for item in expected:
            path = os.path.join(folder, item)
            if os.path.isfile(os.path.join(testFolder, path)) and os.path.splitext(path)[1].lower() in MEDIA_EXT:
                self.assertTrue({'path': path, 'type': 'medium'}
                                in actual['entries'], msg='Path not in response: ' + path)
                expectedCount += 1
                res = call('/api/albums/' + path)
                self.assertEqual(404, res[0])
            if os.path.isdir(os.path.join(testFolder, path)) and item != '.wag':
                self.assertTrue({'path': path, 'type': 'album'}
                                in actual['entries'], msg='Path not in response: ' + path)
                expectedCount += 1
                self.rescurse_albums(path)
        self.assertEqual(len(actual['entries']), expectedCount)

    def test_albums(self):
        self.rescurse_albums('')

        res = call('/api/albums/')
        self.assertEqual(res[0], 200)
        res = call('/api/albums/', method='PUT')
        self.assertEqual(res[0], 404)
        res = call('/api/albums/', method='POST')
        self.assertEqual(res[0], 404)
        res = call('/api/albums/', method='DELETE')
        self.assertEqual(res[0], 404)

        self.assertEqual(call('/api/albums'), call('/api/albums/'))
        res = call('/api/albums/invalid')
        self.assertEqual(res[0], 404)

        res = call('/api/albums/..')
        self.assertEqual(res[0], 404)
        res = call('/api/albums/many')
        self.assertEqual(res[0], 200)
        res = call('/api/albums/many/..')
        self.assertEqual(res[0], 200)
        res = call('/api/albums/many/../..')
        self.assertEqual(res[0], 404)
        res = call('/api/albums/many/../../' + os.path.basename(testFolder))
        self.assertEqual(res[0], 200)
        res = call('/api/albums/many/../../test-sibling')
        self.assertEqual(res[0], 404)
        res = call('/api/albums/many/../../../' +
                   os.path.basename(os.path.dirname(testFolder)))
        self.assertEqual(res[0], 404)

    def rescurse_media(self, folder):
        items = os.listdir(os.path.join(testFolder, folder))
        for item in items:
            path = os.path.join(folder, item)
            ext = os.path.splitext(path)[1].lower()
            if os.path.isfile(os.path.join(testFolder, path)) and (ext in MEDIA_EXT or (ext == '.json' and path.startswith('.wag/'))):
                res = call('/api/media/' + path, decode=False)
                self.assertEqual(res[0], 200)
                if ext == '.json':
                    self.assertTrue('application/json' in res[1])
                else:
                    self.assertTrue(MEDIA_EXT[ext] in res[1])
            if os.path.isdir(os.path.join(testFolder, path)):
                res = call('/api/media/' + path, decode=False)
                self.assertEqual(res[0], 404)
                self.rescurse_media(path)

    def test_media(self):
        self.rescurse_media('')

        res = call('/api/media/image.jpg', decode=False)
        self.assertEqual(res[0], 200)
        res = call('/api/media/image.jpg', decode=False, method='PUT')
        self.assertEqual(res[0], 404)
        res = call('/api/media/image.jpg', decode=False, method='POST')
        self.assertEqual(res[0], 404)
        res = call('/api/media/image.jpg', decode=False, method='DELETE')
        self.assertEqual(res[0], 404)

        res = call('/api/media/invalid')
        self.assertEqual(res[0], 404)
        res = call('/api/media/file.txt')
        self.assertEqual(res[0], 404)
        res = call('/api/media/wag.php')
        self.assertEqual(res[0], 404)

        res = call('/api/media/many/../image.jpg', decode=False)
        self.assertEqual(res[0], 200)
        res = call('/api/media/many/../../' +
                   os.path.basename(testFolder) + '/image.jpg', decode=False)
        self.assertEqual(res[0], 200)
        res = call('/api/media/many/../../test-sibling/image.jpg', decode=False)
        self.assertEqual(res[0], 404)


def main(argv=None):
    if argv is None:
        ourArgv = [sys.argv.pop()]
    else:
        ourArgv = argv
    parser = argparse.ArgumentParser(
        description='Runs PHP tests')
    parser.add_argument('folder',
                        help='folder with test data and wag.php')
    args = parser.parse_args(ourArgv)
    global testFolder
    testFolder = args.folder
    unittest.main()


if __name__ == '__main__':
    main()
