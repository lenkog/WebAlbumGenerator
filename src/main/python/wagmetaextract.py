import argparse
import base64
import hashlib
import importlib
import json
import logging
import math
import os
import sys

import cv2
import imageio
import iptcinfo3
import numpy

logging.getLogger('iptcinfo').disabled = True

canReadVideos = importlib.util.find_spec("imageio_ffmpeg") is not None

WAG_DIR = '.wag'
METADATA_FILE = 'meta.json'
THUMBNAIL_FILE = 'tn.jpg'
THUMBNAIL_SIZE = 125
PINKYNAIL_SIZE = 50
PINKYNAIL_SPACING = 7
ALBUM = 'album'
IMAGE = 'image'
VIDEO = 'video'
IMAGE_EXT = {'.jpg', '.png', '.jpeg', '.gif'}
VIDEO_EXT = {'.mp4', '.mpeg4', '.m4v', '.webm'}
SUBALBUM_PINKYNAIL = imageio.imread(base64.b64decode("""
R0lGODdhMgAyAKEAAAAAAAABAOXl5f///ywAAAAAMgAyAAACzoyPqcsdA8eLtNqL8wASTg2GGfcY
4olCpPSlLraa72zFLU3b+L51Mj/TAYO+G0WATCqXTCasmGlKp8lnyRihap0X4WULRlpZ0fB2/LOY
z10oZq1FYyFwqrxcb97feX37itentPcluESoZjj4R8anKMaYVvFYFTk3QAlZ41ZIiTiZ+XkUahmo
KDok4pWKssqqyvkKCyh74lo7EoubS7urcetbARwcMUzc03ssrKuswtxsTBwDQF1tfY2drb0d05xR
wuIgPj7e4f3tQa6+nlAAADs=
"""))
META_CAPTION = 'caption'
META_COPYRIGHT = 'copyright'
META_DATE = 'date'
META_WIDTH = 'width'
META_HEIGHT = 'height'
META_LAT = 'lat'
META_LON = 'lon'
META_SHUTTER = 'shutter'
META_APERTURE = 'aperture'
META_ISO = 'iso'
META_ZOOM = 'zoom'

processingBase = None
totalItems = 0
thumbnailsGenerated = 0


def isimage(path):
    return os.path.isfile(path) and os.path.splitext(path)[1].lower() in IMAGE_EXT


def isvideo(path):
    return os.path.isfile(path) and os.path.splitext(path)[1].lower() in VIDEO_EXT


def process(path, recurse=False):
    global totalItems

    items = getItems(path)
    totalItems += len(items[ALBUM])
    totalItems += len(items[IMAGE])
    totalItems += sum(map(lambda x: len(x[VIDEO]), items[VIDEO]))
    totalItems += len(list(
        filter(lambda x: x[IMAGE] is not None, items[VIDEO])
    ))
    for album in items[ALBUM]:
        processAlbum(album)
    for image in items[IMAGE]:
        processImage(image)
    for video in items[VIDEO]:
        processVideo(video)
    if recurse:
        for subfolder in items[ALBUM]:
            process(subfolder, recurse)


def getItems(path):
    items = {ALBUM: [], IMAGE: [], VIDEO: []}

    entries = map(lambda x: os.path.join(path, x), filter(
        lambda x: x != WAG_DIR, os.listdir(path)))
    groups = {}
    for entry in entries:
        key = os.path.splitext(os.path.basename(entry))[0]
        group = groups.get(
            key, {ALBUM: [], IMAGE: [], VIDEO: []})
        if isimage(entry):
            group[IMAGE].append(entry)
        elif isvideo(entry):
            group[VIDEO].append(entry)
        elif os.path.isdir(entry):
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


def processAlbum(path):
    global thumbnailsGenerated

    items = getItems(path)
    pinkyNails = []
    if len(items[ALBUM]) > 0:
        pinkyNails.append(makeThumbnail(SUBALBUM_PINKYNAIL, PINKYNAIL_SIZE))
    for image in items[IMAGE]:
        if len(pinkyNails) > 3:
            break
        pinkyNails.append(makeThumbnail(imageio.imread(image), PINKYNAIL_SIZE))
    for video in items[VIDEO]:
        if len(pinkyNails) > 3:
            break
        if video[IMAGE]:
            pinkyNails.append(makeThumbnail(
                imageio.imread(video[IMAGE]), PINKYNAIL_SIZE))
        elif canReadVideos:
            pinkyNails.append(makeThumbnail(
                readFrame(video[VIDEO][0]), PINKYNAIL_SIZE))
    tn = 255 * numpy.ones((THUMBNAIL_SIZE, THUMBNAIL_SIZE, 3), numpy.uint8)
    for i, pinkynail in enumerate(pinkyNails):
        x = PINKYNAIL_SPACING + (i % 2) * (PINKYNAIL_SIZE + PINKYNAIL_SPACING)
        y = PINKYNAIL_SPACING + int(i / 2) * \
            (PINKYNAIL_SIZE + PINKYNAIL_SPACING)
        tn[y:(y + PINKYNAIL_SIZE), x:(x + PINKYNAIL_SIZE)] = pinkynail
    outputThumbnail(tn, path)
    thumbnailsGenerated += 1


def processImage(path):
    global processingBase
    global thumbnailsGenerated

    image = imageio.imread(path)
    outputThumbnail(makeThumbnail(image, THUMBNAIL_SIZE), path)
    outputMeta(extractImageMeta(path, image), path)
    thumbnailsGenerated += 1


def processVideo(group):
    global thumbnailsGenerated

    if not group[IMAGE] and not canReadVideos:
        print('Cannot generate thumbnail for videos:',
              group[VIDEO], file=sys.stderr)
        return
    if group[IMAGE]:
        image = imageio.imread(group[IMAGE])
        tn = makeThumbnail(image, THUMBNAIL_SIZE)
        outputThumbnail(tn, group[IMAGE])
        meta = extractImageMeta(group[IMAGE], image)
        outputMeta(meta, group[IMAGE])
        thumbnailsGenerated += 1
        for video in group[VIDEO]:
            outputThumbnail(tn, video)
            outputMeta(meta, video)
            thumbnailsGenerated += 1
    else:
        for video in group[VIDEO]:
            tn = makeThumbnail(readFrame(video), THUMBNAIL_SIZE)
            outputThumbnail(tn, video)
            outputMeta(extractVideoMeta(video), video)
            thumbnailsGenerated += 1


def readFrame(path):
    if not canReadVideos:
        return None
    frames = imageio.get_reader(path, 'ffmpeg')
    # we want to grab a frame 2 seconds into the video to avoid potential shaking in the beginning
    frameOffset = frames.get_meta_data()['fps'] * 2
    for i, frame in enumerate(frames):
        lastFrame = frame
        if i >= frameOffset:
            break
    return lastFrame


def makeThumbnail(image, size):
    WHITE = [255, 255, 255]
    # crop longest edge
    h, w = image.shape[0:2]
    dw = max(w - min(w, h), 0)
    dh = max(h - min(w, h), 0)
    image = image[math.floor(dh / 2):(h - math.ceil(dh / 2)),
                  math.floor(dw / 2): (w - math.ceil(dw / 2))]
    # scale to size
    h, w = image.shape[0: 2]
    scale = max(h, w, size) / size
    image = cv2.resize(image, (min(math.ceil(w / scale), size),
                               min(math.ceil(h / scale), size)))
    # get rid of alpha channel, replace with white
    if image.shape[2] > 3:
        white = numpy.array(WHITE)
        alpha = (image[:, :, 3] / 255).reshape(image.shape[: 2] + (1,))
        image = ((white * (1 - alpha)) +
                 (image[:, :, :3] * alpha)).astype(numpy.uint8)
    # pad small images
    h, w = image.shape[0:2]
    dh = size - h
    dw = size - w
    image = cv2.copyMakeBorder(image, math.floor(dh / 2), math.ceil(dh / 2),
                               math.floor(dw / 2), math.ceil(dw / 2),
                               cv2.BORDER_CONSTANT, value=WHITE)
    return image


def extractImageMeta(path, image):
    meta = {}
    meta[META_HEIGHT] = image.shape[0]
    meta[META_WIDTH] = image.shape[1]

    iptc = iptcinfo3.IPTCInfo(path)
    if iptc and not iptc.inp_charset:
        iptc = iptcinfo3.IPTCInfo(path, inp_charset='utf-8')
    if iptc:
        entry = iptc['caption/abstract']
        if entry:
            meta[META_CAPTION] = entry
        entry = iptc['copyright notice']
        if entry:
            meta[META_COPYRIGHT] = entry

    exif = image.meta.get('EXIF_MAIN', None)
    if exif:
        entry = exif.get('DateTimeOriginal', None)
        if entry:
            meta[META_DATE] = entry
        entry = exif.get('GPSLatitude', None)
        if entry:
            meta[META_LAT] = entry
        entry = exif.get('GPSLongitude', None)
        if entry:
            meta[META_LON] = entry
        entry = exif.get('ExposureTime', None)
        if entry:
            meta[META_SHUTTER] = entry
        entry = exif.get('FNumber', None)
        if entry:
            meta[META_APERTURE] = entry
        entry = exif.get('ISOSpeedRatings', None)
        if entry:
            meta[META_ISO] = entry
        entry = exif.get('FocalLengthIn35mmFilm', None)
        if entry:
            meta[META_ZOOM] = entry
    return meta


def extractVideoMeta(path):
    if not canReadVideos:
        return {}
    meta = {}
    frames = imageio.get_reader(path, 'ffmpeg')
    entry = frames.get_meta_data().get('size', None)
    if entry:
        meta[META_HEIGHT] = entry[1]
        meta[META_WIDTH] = entry[0]
    return meta


def outputMeta(meta, path):
    dst = getMetaDir(path)
    if not os.path.exists(dst):
        os.makedirs(dst)
    with open(os.path.join(dst, METADATA_FILE), 'w', encoding='utf-8') as metaFile:
        json.dump(meta, metaFile, ensure_ascii=False, indent=4)


def outputThumbnail(image, path):
    dst = getMetaDir(path)
    if not os.path.exists(dst):
        os.makedirs(dst)
    imageio.imwrite(os.path.join(dst, THUMBNAIL_FILE), image)


def getMetaDir(path):
    global processingBase

    relPath = os.path.relpath(path, processingBase)
    hash = hashlib.md5(relPath.encode('utf-8')).hexdigest()
    return os.path.join(processingBase, WAG_DIR, hash)


def main(argv=None):
    global processingBase
    global totalItems
    global thumbnailsGenerated

    if argv is None:
        ourArgv = sys.argv[1:]
    else:
        ourArgv = argv
    parser = argparse.ArgumentParser(
        description='Process a folder to extract metadata for WebAlbumGenarator')
    parser.add_argument('folder', nargs='+',
                        help='folder to process')
    parser.add_argument('-r', dest='recursive', action='store_true',
                        help='recurse into subfolders')
    args = parser.parse_args(ourArgv)
    for folder in args.folder:
        processingBase = folder
        totalItems = 0
        thumbnailsGenerated = 0
        print('Processing folder', processingBase, '...')
        process(folder, args.recursive)
        print('Total items:', totalItems)
        print('Thumbnails generated:', thumbnailsGenerated)


if __name__ == "__main__":
    main()