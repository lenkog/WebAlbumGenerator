import argparse
import json
import os
import sys

import imageio
import numpy

METADATA_FILE = 'meta.json'
THUMBNAIL_FILE = 'tn.jpg'


def assertRecursive(expectedPath, actualPath):
    expected = os.listdir(expectedPath)
    actual = os.listdir(actualPath)
    assert actual == expected, 'Dissimilar folders: ' + \
        expectedPath + ' and ' + actualPath
    for item in expected:
        path = os.path.join(expectedPath, item)
        if os.path.isdir(path):
            assertRecursive(path, os.path.join(actualPath, item))
        elif os.path.isfile(path):
            if item == METADATA_FILE:
                assertMeta(path, os.path.join(actualPath, item))
            elif item == THUMBNAIL_FILE:
                assertThumbnail(path, os.path.join(actualPath, item))
            else:
                assert False, 'Unexpected file: ' + path


def assertMeta(expectedMeta, actualMeta):
    expected = None
    actual = None
    with open(expectedMeta, encoding='utf-8') as f:
        expected = json.dumps(json.load(f), sort_keys=True)
    with open(actualMeta, encoding='utf-8') as f:
        actual = json.dumps(json.load(f), sort_keys=True)
    assert actual == expected, 'Dissimilar metadata: ' + \
        expectedMeta + ' and ' + actualMeta


def assertThumbnail(expectedThumbnail, actualThumbnail):
    expectedImage = imageio.imread(expectedThumbnail)
    actualImage = imageio.imread(actualThumbnail)
    assert actualImage.shape == expectedImage.shape, 'Dissimilar thumbnail dimensions: ' + \
        expectedThumbnail + ' and ' + actualThumbnail
    err = numpy.sum((expectedImage.astype("float") -
                     actualImage.astype("float")) ** 2)
    err /= float(expectedImage.shape[0] * expectedImage.shape[1])
    assert err < 100, 'Dissimilar thumbnails: ' + \
        expectedThumbnail + ' and ' + actualThumbnail


def main(argv=None):
    if argv is None:
        ourArgv = sys.argv[1:]
    else:
        ourArgv = argv
    parser = argparse.ArgumentParser(
        description='Assert that two folders contain the same WebAlbumGenarator metadata')
    parser.add_argument('expected',
                        help='folder with expected metadata')
    parser.add_argument('actual',
                        help='folder with actual metadata')
    args = parser.parse_args(ourArgv)
    assertRecursive(args.expected, args.actual)


if __name__ == '__main__':
    main()
