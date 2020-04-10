import md5 from 'blueimp-md5';
import { METADATA_FILE, THUMBNAIL_FILE, WAG_CONTAINER_ID, WAG_DIR } from "./constants";
import { Dim2D, ItemType, AlbumListing, Navigation } from "./models";

const IMAGE_EXT: Map<string, string> = new Map([
    ['jpg', 'image/jpeg'],
    ['png', 'image/png'],
    ['jpeg', 'image/jpeg'],
    ['gif', 'image/gif'],
]);

const VIDEO_EXT: Map<string, string> = new Map([
    ['webm', 'video/webm'],
    ['mp4', 'video/mp4'],
    ['mpeg4', 'video/mp4'],
    ['m4v', 'video/mp4'],
]);

export function dirname(path: string) {
    return path.split('/').slice(0, -1).join('/');
}

export function basename(path: string) {
    return path.split('/').pop();
}

export function filename(path: string) {
    if (path === '..') {
        return '';
    }
    let parts = basename(path).split('.');
    return parts.length == 1 ? parts[0] : parts.slice(0, -1).join('.');
}

export function extname(path: string) {
    let parts = basename(path).split('.');
    return parts.length == 1 ? '' : parts.pop();
}

export function guessMediaType(path: string) {
    let ext = extname(path).toLowerCase();
    if (IMAGE_EXT.has(ext)) {
        return ItemType.IMAGE;
    } else if (VIDEO_EXT.has(ext)) {
        return ItemType.VIDEO;
    } else {
        return null;
    }
}

export function videoMIME(path: string) {
    return VIDEO_EXT.get(extname(path).toLowerCase());
}

export function getMetaId(path: string) {
    return md5(path);
}

export function getThumbnailURL(prefix: string, path: string) {
    return prefix + urlencodeSegments(WAG_DIR + '/' + getMetaId(path) + '/' + THUMBNAIL_FILE);
}

export function getMetaURL(prefix: string, path: string) {
    return prefix + urlencodeSegments(WAG_DIR + '/' + getMetaId(path) + '/' + METADATA_FILE);
}

export function getMediaURL(prefix: string, path: string) {
    return prefix + urlencodeSegments(path);
}

export function trailingPath(prefix: string, fullPath: string): string {
    if (
        fullPath.length < prefix.length ||
        fullPath.substring(0, prefix.length) !== prefix ||
        (fullPath.length > prefix.length &&
            fullPath.charAt(prefix.length) !== '/')
    ) {
        throw new Error('Path doesn\'t start with prefix "' + prefix + '": ' + fullPath);
    }
    return fullPath.substring(prefix.length + (fullPath.length > prefix.length ? 1 : 0));
}

export function getAvailableArea(element: Element): Dim2D {
    let bodyRect = document.body.getBoundingClientRect();
    let bodyStyle = window.getComputedStyle(document.body);
    let containerStyle = window.getComputedStyle(document.getElementById(WAG_CONTAINER_ID));
    let elementRect = element.getBoundingClientRect();
    return {
        w: Math.max(
            100,
            window.innerWidth -
            parseInt(bodyStyle.marginLeft, 10) -
            parseInt(bodyStyle.marginRight, 10) -
            parseInt(bodyStyle.borderLeftWidth, 10) -
            parseInt(bodyStyle.borderRightWidth, 10) -
            parseInt(bodyStyle.paddingLeft, 10) -
            parseInt(bodyStyle.paddingRight, 10) -
            parseInt(containerStyle.marginRight, 10) -
            parseInt(containerStyle.borderRightWidth, 10) -
            parseInt(containerStyle.paddingRight, 10) -
            10 // for scrollbar
        ),
        h: Math.max(
            100,
            window.innerHeight -
            elementRect.top +
            bodyRect.top -
            parseInt(bodyStyle.marginTop, 10) -
            parseInt(bodyStyle.marginBottom, 10) -
            parseInt(bodyStyle.borderTopWidth, 10) -
            parseInt(bodyStyle.borderBottomWidth, 10) -
            parseInt(bodyStyle.paddingTop, 10) -
            parseInt(bodyStyle.paddingBottom, 10) -
            parseInt(containerStyle.marginBottom, 10) -
            parseInt(containerStyle.borderBottomWidth, 10) -
            parseInt(containerStyle.paddingBottom, 10)
        ),
    };
}

export function urlencodeSegments(path: string) {
    if (typeof path === 'undefined') {
        return undefined;
    }
    return path.split('/').map(encodeURIComponent).join('/');
}

export function urldecodeSegments(path: string) {
    if (typeof path === 'undefined') {
        return undefined;
    }
    return path.split('/').map(decodeURIComponent).join('/');
}

export function getNavigation(elements: string[], isOfInterest: (e: string) => boolean, mapper: (e: string) => string) {
    let isElementEncountered = false;
    let prev: string = null;
    let next: string = null;
    for (let e of elements) {
        if (isOfInterest(e)) {
            isElementEncountered = true;
            continue;
        }
        if (!isElementEncountered) {
            prev = e;
        } else {
            next = e;
            break;
        }
    }
    return new Navigation(mapper(prev), mapper(next));
}