import axios from 'axios';
import { AlbumListing } from './models';
import { urlencodeSegments } from './utils';

declare const API_ENDPOINT: string;

enum RESOURCES {
    ALBUMS = '/albums',
    ASSETS = '/assets',
};

export enum ASSETS {
    OVERLAY_ALBUM = 'overlay-album',
    OVERLAY_VIDEO = 'overlay-video',
    DEFAULT_THUMBNAIL = 'default-thumbnail',
    BTN_MENU = "btn-menu",
    BTN_BACK = "btn-back",
    BTN_PREV = "btn-prev",
    BTN_NEXT = "btn-next",
}

function processError(error: any, userSuppliedHandler: (error: string) => void = null) {
    if (axios.isCancel(error)) {
        // cancelled requests are OK
        return;
    }
    let message;
    if (error.response) {
        message = error.response.data;
    } else if (error.request) {
        message = String(error.request);
    } else {
        message = error.message;
    }
    if (userSuppliedHandler !== null) {
        userSuppliedHandler(message);
    } else {
        console.error('XHR error', message);
    }
}

export function getContent(url: string, onSuccess: (data: any) => void, onError: (error: string) => void = null, beforeRequest: () => void = null) {
    if (beforeRequest !== null) {
        beforeRequest();
    }
    let requestCanceller;
    let requestOptions = {
        cancelToken: new axios.CancelToken((c) => requestCanceller = c)
    };
    axios.get(url, requestOptions).then(
        (response) => onSuccess(response.data),
        (error) => processError(error, onError),
    );
    return requestCanceller;
}

export function getAlbumListing(path: string, onSuccess: (model: AlbumListing) => void, onError: (error: string) => void = null, beforeRequest: () => void = null) {
    if (beforeRequest !== null) {
        beforeRequest();
    }
    let requestCanceller;
    let requestOptions = {
        cancelToken: new axios.CancelToken((c) => requestCanceller = c)
    };
    axios.get(API_ENDPOINT + RESOURCES.ALBUMS + '/' + urlencodeSegments(path), requestOptions).then(
        (response) => onSuccess(<AlbumListing>response.data),
        (error) => processError(error, onError),
    );
    return requestCanceller;
}

export function getAssetURL(name: string) {
    return API_ENDPOINT + RESOURCES.ASSETS + '/' + name;
}
