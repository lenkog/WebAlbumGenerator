import argparse
import base64
import collections
import hashlib
import importlib
import json
import logging
import math
import numbers
import os
import sys
from datetime import datetime

import cv2
import dateutil.parser
import imageio
import iptcinfo3
import numpy

logging.getLogger('iptcinfo').disabled = True

canReadVideos = importlib.util.find_spec('imageio_ffmpeg') is not None

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
META_ITEMS = 'items'
META_CAPTION = 'caption'
META_COPYRIGHT = 'copyright'
META_DATE = 'date'
META_WIDTH = 'width'
META_HEIGHT = 'height'
META_SIZE = 'size'
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


def process(path):
    global totalItems

    totalItems += 1
    processAlbum(path)

    items = getItems(path)
    totalItems += len(items[IMAGE])
    totalItems += sum(map(lambda x: len(x[VIDEO]), items[VIDEO]))
    totalItems += len(list(
        filter(lambda x: x[IMAGE] is not None, items[VIDEO])
    ))

    for image in items[IMAGE]:
        processImage(image)
    for video in items[VIDEO]:
        processVideo(video)
    for subfolder in items[ALBUM]:
        process(subfolder)


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
    for image in sorted(items[IMAGE]):
        if len(pinkyNails) > 3:
            break
        pinkyNails.append(makeThumbnail(imageio.imread(image), PINKYNAIL_SIZE))
    for video in sorted(items[VIDEO], key=lambda video: sorted(video[VIDEO])[0]):
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
    outputMeta(extractAlbumMeta(path), path)
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
            videoMeta = extractVideoMeta(video)
            videoMeta.update(meta)
            outputMeta(videoMeta, video)
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
    if len(image.shape) > 2 and image.shape[2] > 3:
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
    # make sure we produce an RGB image
    if len(image.shape) < 3 or image.shape[2] < 3:
        image = cv2.cvtColor(image, cv2.COLOR_GRAY2RGB)
    return image


def extractAlbumMeta(path):
    meta = {}

    meta[META_CAPTION] = os.path.basename(path)
    meta[META_ITEMS] = {}
    items = getItems(path)
    for album in items[ALBUM]:
        itemId = getMetaId(album)
        meta[META_ITEMS][itemId] = {
            META_CAPTION: os.path.basename(album),
            META_DATE: getLatestAlbumItemDate(album).isoformat(' '),
        }
    for image in items[IMAGE]:
        itemId = getMetaId(image)
        meta[META_ITEMS][itemId] = trimToAlbumItemMeta(
            extractImageMeta(image, imageio.imread(image)))
    for video in items[VIDEO]:
        for itemVideo in video[VIDEO]:
            itemId = getMetaId(itemVideo)
            meta[META_ITEMS][itemId] = trimToAlbumItemMeta(
                extractVideoMeta(itemVideo))
        if video[IMAGE]:
            posterMeta = trimToAlbumItemMeta(extractImageMeta(
                video[IMAGE], imageio.imread(video[IMAGE])))
            for itemVideo in video[VIDEO]:
                itemId = getMetaId(itemVideo)
                meta[META_ITEMS][itemId].update(posterMeta)
            posterId = getMetaId(video[IMAGE])
            meta[META_ITEMS][posterId] = posterMeta

    return meta


def getLatestAlbumItemDate(path):
    latestDate = datetime.fromtimestamp(0)
    items = getItems(path)
    for album in items[ALBUM]:
        latestDate = max(getLatestAlbumItemDate(album), latestDate)
    for image in items[IMAGE]:
        metaDate = extractImageMeta(image, imageio.imread(image))[META_DATE]
        latestDate = max(dateutil.parser.isoparse(metaDate), latestDate)
    for video in items[VIDEO]:
        if video[IMAGE]:
            metaDate = extractImageMeta(
                video[IMAGE], imageio.imread(video[IMAGE]))[META_DATE]
            latestDate = max(dateutil.parser.isoparse(metaDate), latestDate)
        else:
            for itemVideo in video[VIDEO]:
                metaDate = extractVideoMeta(itemVideo)[META_DATE]
                latestDate = max(
                    dateutil.parser.isoparse(metaDate), latestDate)
    if latestDate == datetime.fromtimestamp(0):
        latestDate = datetime.fromtimestamp(os.path.getmtime(path))
    return latestDate


def trimToAlbumItemMeta(fullMeta):
    meta = {
        META_CAPTION: fullMeta[META_CAPTION],
        META_DATE: fullMeta[META_DATE],
    }
    return meta


def extractImageMeta(path, image):
    meta = {}
    meta[META_HEIGHT] = image.shape[0]
    meta[META_WIDTH] = image.shape[1]

    iptc = iptcinfo3.IPTCInfo(path)
    if iptc and not iptc.inp_charset:
        iptc = iptcinfo3.IPTCInfo(path, inp_charset='utf-8')
    if iptc:
        entry = iptc['caption/abstract']
        if entry and len(entry.strip()) > 0:
            meta[META_CAPTION] = entry
        entry = iptc['copyright notice']
        if entry and len(entry.strip()) > 0:
            meta[META_COPYRIGHT] = entry

    exif = image.meta.get('EXIF_MAIN', None)
    if exif is not None:
        entry = exif.get('DateTimeOriginal', None)
        if entry is not None:
            meta[META_DATE] = datetime.strptime(
                entry, '%Y:%m:%d %H:%M:%S').isoformat(' ')
        entry = exif.get('ImageDescription', None)
        if entry is not None and not (META_CAPTION in meta) and len(entry.strip()) > 0:
            meta[META_CAPTION] = entry
        entry = exif.get('Copyright', None)
        if entry is not None and not (META_COPYRIGHT in meta) and len(entry.strip()) > 0:
            meta[META_COPYRIGHT] = entry
        entry = exif.get('Artist', None)
        if entry is not None and not (META_COPYRIGHT in meta) and len(entry.strip()) > 0:
            meta[META_COPYRIGHT] = entry
        entry = exif.get('GPSInfo', None)
        if entry is not None and len(entry) > 4:
            lat = exifDegreeToDecimal(entry[2])
            if entry[1] == 'S':
                lat *= -1
            lon = exifDegreeToDecimal(entry[4])
            if entry[3] == 'W':
                lon *= -1
            meta[META_LAT] = lat
            meta[META_LON] = lon
        entry = exif.get('ExposureTime', None)
        if entry is not None:
            frac = exifFracToNum(entry)
            if frac is not None:
                if frac == 0:
                    meta[META_SHUTTER] = '0'
                else:
                    denominator = int(1 / frac)
                    meta[META_SHUTTER] = '1/' + str(denominator)
        entry = exif.get('FNumber', None)
        if entry is not None:
            frac = exifFracToNum(entry)
            if frac is not None:
                meta[META_APERTURE] = round(frac, 2)
        entry = exif.get('ISOSpeedRatings', None)
        if entry is not None:
            meta[META_ISO] = entry
        entry = exif.get('FocalLengthIn35mmFilm', None)
        if entry is not None:
            meta[META_ZOOM] = entry

    if not (META_CAPTION in meta):
        meta[META_CAPTION] = os.path.basename(path)
    if not (META_DATE in meta):
        meta[META_DATE] = datetime.fromtimestamp(
            os.path.getmtime(path)).isoformat(' ')

    return meta


def exifFracToNum(fraction):
    if isinstance(fraction, collections.Sequence) and len(fraction) == 2 and float(fraction[1]) != 0:
        return float(fraction[0]) / float(fraction[1])
    elif isinstance(fraction, (numbers.Number, str)):
        return float(fraction)
    return None


def exifDegreeToDecimal(degrees):
    if isinstance(degrees, collections.Sequence) and len(degrees) == 3:
        return exifFracToNum(degrees[0]) + exifFracToNum(degrees[1]) / 60 + exifFracToNum(degrees[2]) / 3600
    elif isinstance(degrees, (numbers.Number, str)):
        return float(degrees)
    return None


def extractVideoMeta(path):
    if not canReadVideos:
        return {}
    meta = {}

    frames = imageio.get_reader(path, 'ffmpeg')
    entry = frames.get_meta_data().get('size', None)
    if entry is not None:
        meta[META_HEIGHT] = entry[1]
        meta[META_WIDTH] = entry[0]
    meta[META_SIZE] = os.path.getsize(path)

    if not (META_CAPTION in meta):
        meta[META_CAPTION] = os.path.basename(path)
    if not (META_DATE in meta):
        meta[META_DATE] = datetime.fromtimestamp(
            os.path.getmtime(path)).isoformat(' ')

    return meta


def outputMeta(meta, path):
    dst = getMetaDir(path)
    if not os.path.exists(dst):
        os.makedirs(dst)
    with open(os.path.join(dst, METADATA_FILE), 'w', encoding='utf-8') as metaFile:
        json.dump(meta, metaFile, ensure_ascii=False, indent=4, sort_keys=True)


def outputThumbnail(image, path):
    dst = getMetaDir(path)
    if not os.path.exists(dst):
        os.makedirs(dst)
    imageio.imwrite(os.path.join(dst, THUMBNAIL_FILE), image)


def getMetaDir(path):
    global processingBase

    return os.path.join(processingBase, WAG_DIR, getMetaId(path))


def getMetaId(path):
    global processingBase

    relPath = os.path.relpath(path, processingBase)
    if relPath == '.':
        relPath = ''
    return hashlib.md5(relPath.encode('utf-8')).hexdigest()


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
    parser.add_argument('folder',
                        help='folder to process')
    args = parser.parse_args(ourArgv)
    processingBase = args.folder
    totalItems = 0
    thumbnailsGenerated = 0
    process(processingBase)
    print('Total items:', totalItems)
    print('Thumbnails generated:', thumbnailsGenerated)


if __name__ == "__main__":
    main()
