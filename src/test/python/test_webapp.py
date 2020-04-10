import argparse
import hashlib
import json
import os
import pty
import subprocess
import sys
import unittest
import urllib

from selenium import webdriver
from selenium.webdriver.support.ui import WebDriverWait

MAX_ITEMS_TO_CHECK = 25

URL_SAFE_CHARS = '/\'()'
URL_SAFE_CHARS_RELAXED = '/\'()[]{}^'
ALBUM = 'album'
IMAGE = 'image'
VIDEO = 'video'
MEDIA = 'media'
NAME = 'name'
LINK = 'link'
ICON = 'icon'
ENABLED = 'enabled'
IMAGE_EXT = {'.jpg', '.png', '.jpeg', '.gif'}
VIDEO_EXT = {'.mp4', '.mpeg4', '.m4v', '.webm'}
EXT_2_MIME = {
    '.jpg': 'image/jpeg',
    '.png': 'image/png',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.webm': 'video/webm',
    '.mp4': 'video/mp4',
    '.mpeg4': 'video/mp4',
    '.m4v': 'video/mp4',
}
ROOT_CAPTION = 'Gallery'
THUMBNAIL_SIZE = 125


def isimage(path):
    return os.path.isfile(path) and os.path.splitext(path)[1].lower() in IMAGE_EXT


def isvideo(path):
    return os.path.isfile(path) and os.path.splitext(path)[1].lower() in VIDEO_EXT


def getMedia(pathPrefix, path):
    return sorted(map(lambda x: os.path.join(path, x), filter(
        lambda x: x != '.wag' and (isimage(os.path.join(
            pathPrefix, path, x)) or isvideo(os.path.join(pathPrefix, path, x))),
        os.listdir(os.path.join(pathPrefix, path)))))


def getItems(pathPrefix, path):
    items = {ALBUM: [], IMAGE: [], VIDEO: []}

    entries = map(lambda x: os.path.join(path, x), filter(
        lambda x: x != '.wag', os.listdir(os.path.join(pathPrefix, path))))
    groups = {}
    for entry in entries:
        key = os.path.splitext(os.path.basename(entry))[0]
        group = groups.get(
            key, {ALBUM: [], IMAGE: [], VIDEO: []})
        if isimage(os.path.join(pathPrefix, entry)):
            group[IMAGE].append(entry)
        elif isvideo(os.path.join(pathPrefix, entry)):
            group[VIDEO].append(entry)
        elif os.path.isdir(os.path.join(pathPrefix, entry)):
            group[ALBUM].append(entry)
        groups[key] = group
    for group in groups.values():
        for album in group[ALBUM]:
            items[ALBUM].append(album)
        if len(group[VIDEO]) > 0:
            poster = None
            if len(group[IMAGE]) > 0:
                poster = group[IMAGE][0]
            items[VIDEO].append({
                VIDEO: group[VIDEO],
                IMAGE: poster
            })
        else:
            for image in group[IMAGE]:
                items[IMAGE].append(image)
    return items


def getAlbumEntries(items):
    entries = {ALBUM: [], MEDIA: []}
    entries[ALBUM] = sorted(items[ALBUM])
    entries[MEDIA] = sorted(
        items[IMAGE] + list(map(lambda v: sorted(v[VIDEO])[0], items[VIDEO])))
    return entries


def metaId(path):
    return hashlib.md5(path.encode('utf-8')).hexdigest()


def getPrevNext(items, isOfInterest):
    isEncountered = False
    prv = None
    nxt = None
    for item in items:
        if isOfInterest(item):
            isEncountered = True
            continue
        if not isEncountered:
            prv = item
        else:
            nxt = item
            break
    return (prv, nxt)


class TestApp(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        master, slave = pty.openpty()
        cls.server = subprocess.Popen(
            ['php', '-S', 'localhost:8000'], cwd=os.path.dirname(testFolder), stdout=slave, stderr=slave, close_fds=True)
        with os.fdopen(master) as stdout:
            line = stdout.readline()
            while 'http://localhost:8000' not in line:
                line = stdout.readline()

        cls.browser = webdriver.Firefox(service_log_path=os.devnull)
        cls.browser.set_window_size(1024, 768)
        cls.wait = WebDriverWait(cls.browser, timeout=10, poll_frequency=0.25)
        cls.wagURL = 'http://localhost:8000/' + \
            os.path.basename(testFolder) + '/wag.php'
        cls.mediaURL = cls.wagURL + '/api/media/'

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.browser.quit()

    def _isViewLoaded(self, browser, caption):
        return browser.find_element_by_class_name('wagCaption').text != 'Loading...' and browser.title == caption

    def _getMeta(self, folder):
        meta = None
        metaFile = os.path.join(
            testFolder, '.wag', metaId(folder), 'meta.json')
        if os.path.isfile(metaFile):
            with open(metaFile) as f:
                meta = json.load(f)
        return meta

    def _getCaption(self, folder, meta):
        if meta:
            caption = meta['caption']
        else:
            caption = os.path.basename(folder)
        if folder == '':
            caption = ROOT_CAPTION
        return caption

    def _getAlbumLink(self, album, safeChars=URL_SAFE_CHARS):
        if album is not None:
            if album == '':
                album = self.wagURL + '/#/album'
            else:
                album = self.wagURL + '/#/album/' + \
                    urllib.parse.quote(album, safeChars)
        return album

    def _getItemLink(self, item, safeChars=URL_SAFE_CHARS):
        if item is not None:
            item = self.wagURL + '/#/item/' + \
                urllib.parse.quote(item, safeChars)
        return item

    def test_home(self):
        self.browser.get(self.wagURL)
        self.browser.refresh()
        self.wait.until(lambda d: self._isViewLoaded(d, ROOT_CAPTION))
        self.browser.get(self.wagURL + '/')
        self.browser.refresh()
        self.wait.until(lambda d: self._isViewLoaded(d, ROOT_CAPTION))
        self.browser.get(self.wagURL + '#/')
        self.browser.refresh()
        self.wait.until(lambda d: self._isViewLoaded(d, ROOT_CAPTION))
        self.browser.get(self.wagURL + '/#/')
        self.browser.refresh()
        self.wait.until(lambda d: self._isViewLoaded(d, ROOT_CAPTION))
        self.browser.get(self.wagURL + '#/album')
        self.browser.refresh()
        self.wait.until(lambda d: self._isViewLoaded(d, ROOT_CAPTION))
        self.browser.get(self.wagURL + '/#/album')
        self.browser.refresh()
        self.wait.until(lambda d: self._isViewLoaded(d, ROOT_CAPTION))
        self.browser.get(self.wagURL + '#/album/')
        self.browser.refresh()
        self.wait.until(lambda d: self._isViewLoaded(d, ROOT_CAPTION))
        self.browser.get(self.wagURL + '/#/album/')
        self.browser.refresh()
        self.wait.until(lambda d: self._isViewLoaded(d, ROOT_CAPTION))

    def _validateAction(self, action, actionElement):
        if not action[ENABLED]:
            icon = actionElement.find_element_by_tag_name('img')
            self.assertTrue(action[ICON] in icon.get_attribute('src'))
            self.assertTrue('wagBtnDisabled' in icon.get_attribute('class'))
        else:
            link = actionElement.find_element_by_tag_name('a')
            icon = link.find_element_by_tag_name('img')
            self.assertEqual(link.get_attribute('href'), action[LINK])
            self.assertTrue(action[ICON] in icon.get_attribute('src'))
            self.assertEqual(icon.get_attribute('title'), action[NAME])
            self.assertEqual(icon.get_attribute('alt'), action[NAME])
            self.assertTrue(
                'wagBtnDisabled' not in icon.get_attribute('class'))

    def _validateHeader(self, folder, caption, actions):
        self.assertEqual(self.browser.title, caption)
        self.assertEqual(self.browser.find_element_by_class_name(
            'wagCaption').text, caption)
        actionElements = self.browser.find_elements_by_class_name('wagAction')
        self.assertTrue(len(actionElements) > 0)
        self._validateAction(
            {NAME: 'Menu', LINK: None, ICON: '/api/assets/btn-menu', ENABLED: False}, actionElements[-1])
        if folder == '':
            customActionsStartIdx = 0
            self.assertEqual(len(actionElements), len(actions) + 1)
        else:
            customActionsStartIdx = 1
            self.assertEqual(len(actionElements), len(actions) + 2)
            parentAlbums = folder.split('/')
            if len(parentAlbums) > 1:
                self._validateAction({
                    NAME: 'Back to ' + parentAlbums[-2],
                    LINK: self._getAlbumLink('/'.join(parentAlbums[0:-1]), URL_SAFE_CHARS_RELAXED),
                    ICON: '/api/assets/btn-back',
                    ENABLED: True,
                }, actionElements[0])
            else:
                self._validateAction({
                    NAME: 'Back to ' + ROOT_CAPTION,
                    LINK: self._getAlbumLink('', URL_SAFE_CHARS_RELAXED),
                    ICON: '/api/assets/btn-back',
                    ENABLED: True,
                }, actionElements[0])
        for i, action in enumerate(actions):
            self._validateAction(
                action, actionElements[i + customActionsStartIdx])

    def _rescurse_albums(self, folder):
        items = getAlbumEntries(getItems(testFolder, folder))
        meta = self._getMeta(folder)
        caption = self._getCaption(folder, meta)

        self.browser.get(self._getAlbumLink(folder))
        self.browser.refresh()
        self.wait.until(lambda d: self._isViewLoaded(d, caption))

        self._validateHeader(folder, caption, [])

        slabs = self.browser.find_element_by_id(
            'wagSlabs').find_elements_by_class_name('wagSlab')

        slabCount = len(slabs)
        expectedCount = len(items[ALBUM]) + len(items[MEDIA])
        self.assertEqual(slabCount, expectedCount, folder)

        for i, slab in enumerate(slabs):
            if i < len(items[ALBUM]):
                item = items[ALBUM][i]
            else:
                item = items[MEDIA][i - len(items[ALBUM])]
            if meta:
                itemCaption = meta['items'][metaId(item)]['caption']
            else:
                itemCaption = os.path.basename(item)

            link = slab.find_element_by_class_name('wagSlabLink')
            linkURL = link.get_attribute('href')

            thumbnail = link.find_element_by_class_name('wagSlabThumbnail')
            self.assertEqual(thumbnail.get_attribute('title'), itemCaption)
            self.assertEqual(thumbnail.get_attribute('alt'), itemCaption)
            height = self.browser.get_window_size()['height']
            if thumbnail.location['y'] > height - THUMBNAIL_SIZE:
                # images should be lazy-loaded by v-lazy-image
                self.assertEqual(thumbnail.get_attribute('src'), '//:0')
            else:
                if os.path.isfile(os.path.join(testFolder, '.wag', metaId(folder), 'tn.jpg')):
                    self.assertEqual(thumbnail.get_attribute(
                        'src'), self.mediaURL + '.wag/' + metaId(item) + '/tn.jpg')
                else:
                    self.assertEqual(thumbnail.get_attribute(
                        'src'), self.wagURL + '/api/assets/default-thumbnail')

            if i < len(items[ALBUM]):
                self.assertEqual(linkURL, self._getAlbumLink(item))
                overlay = link.find_element_by_class_name('wagSlabOverlay')
                self.assertEqual(overlay.get_attribute('src'),
                                 self.wagURL + '/api/assets/overlay-album')
                self.assertEqual(overlay.get_attribute('title'), itemCaption)
                self.assertEqual(slab.text, itemCaption)
            else:
                self.assertEqual(linkURL, self._getItemLink(item))
                if os.path.splitext(item)[1].lower() in VIDEO_EXT:
                    overlay = link.find_element_by_class_name('wagSlabOverlay')
                    self.assertEqual(overlay.get_attribute(
                        'src'), self.wagURL + '/api/assets/overlay-video')
                    self.assertEqual(
                        overlay.get_attribute('title'), itemCaption)
                else:
                    self.assertEqual(
                        len(link.find_elements_by_class_name('wagSlabOverlay')), 0)
                self.assertEqual(slab.text, '')

        for album in items[ALBUM]:
            self._rescurse_albums(album)

    def testAlbum(self):
        self._rescurse_albums('')

    def _rescurse_items(self, folder):
        items = getItems(testFolder, folder)
        media = getMedia(testFolder, folder)

        for item in items[IMAGE][0:MAX_ITEMS_TO_CHECK]:
            meta = self._getMeta(item)
            caption = self._getCaption(item, meta)

            self.browser.get(self._getItemLink(item))
            self.browser.refresh()
            self.wait.until(lambda d: self._isViewLoaded(d, caption))

            (prv, nxt) = getPrevNext(media, lambda x: x == item)
            prv = self._getItemLink(prv)
            nxt = self._getItemLink(nxt)
            self._validateHeader(item, caption, [
                {NAME: 'Previous', LINK: prv, ICON: '/api/assets/btn-prev',
                    ENABLED: prv is not None},
                {NAME: 'Next', LINK: nxt, ICON: '/api/assets/btn-next',
                    ENABLED: nxt is not None},
            ])

            image = self.browser.find_element_by_class_name('wagImage')
            self.assertEqual(image.get_attribute(
                'src'), self.mediaURL + urllib.parse.quote(item, safe=URL_SAFE_CHARS))
            self.assertEqual(image.get_attribute('title'), caption)
            self.assertEqual(image.get_attribute('alt'), caption)

            windowSize = self.browser.get_window_size()
            self.assertLess(image.size['height'], windowSize['height'])
            self.assertLess(image.size['width'], windowSize['width'])

        for item in items[VIDEO][0:MAX_ITEMS_TO_CHECK]:
            videos = sorted(item[VIDEO])
            leadVideo = videos[0]
            commonName = os.path.splitext(os.path.basename(leadVideo))[0]
            poster = item[IMAGE]
            alternatives = videos.copy()
            if poster:
                alternatives.append(poster)

            for alt in alternatives:
                meta = self._getMeta(alt)
                caption = self._getCaption(alt, meta)

                self.browser.get(self._getItemLink(alt))
                self.browser.refresh()
                self.wait.until(lambda d: self._isViewLoaded(d, caption))

                (prv, nxt) = getPrevNext(media, lambda x: os.path.splitext(
                    os.path.basename(x))[0] == commonName)
                prv = self._getItemLink(prv)
                nxt = self._getItemLink(nxt)
                self._validateHeader(alt, caption, [
                    {NAME: 'Previous', LINK: prv, ICON: '/api/assets/btn-prev',
                        ENABLED: prv is not None},
                    {NAME: 'Next', LINK: nxt, ICON: '/api/assets/btn-next',
                        ENABLED: nxt is not None},
                ])

                video = self.browser.find_element_by_class_name('wagVideo')
                if poster:
                    self.assertEqual(video.get_attribute(
                        'poster'), self.mediaURL + urllib.parse.quote(poster, safe=URL_SAFE_CHARS))
                else:
                    self.assertEqual(video.get_attribute('poster'), '')
                sources = video.find_elements_by_tag_name('source')
                self.assertEqual(len(sources), len(videos))
                for i, source in enumerate(sources):
                    self.assertEqual(source.get_attribute(
                        'src'), self.mediaURL + urllib.parse.quote(videos[i], safe=URL_SAFE_CHARS))
                    self.assertEqual(source.get_attribute(
                        'type'), EXT_2_MIME[os.path.splitext(videos[i])[1].lower()])
                link = video.find_element_by_tag_name('a')
                self.assertEqual(link.get_attribute(
                    'href'), self.mediaURL + urllib.parse.quote(leadVideo, safe=URL_SAFE_CHARS))

                windowSize = self.browser.get_window_size()
                self.assertLess(video.size['height'], windowSize['height'])
                self.assertLess(video.size['width'], windowSize['width'])

        for album in items[ALBUM]:
            self._rescurse_items(album)

    def testItems(self):
        self._rescurse_items('')


def main(argv=None):
    if argv is None:
        ourArgv = sys.argv[1:]
    else:
        ourArgv = argv
    parser = argparse.ArgumentParser(description='Runs web app tests')
    parser.add_argument('folder',
                        help='folder with test data and wag.php')
    args = parser.parse_args(ourArgv)
    global testFolder
    testFolder = args.folder
    sys.argv = sys.argv[0:1]
    unittest.main()


if __name__ == '__main__':
    main()
