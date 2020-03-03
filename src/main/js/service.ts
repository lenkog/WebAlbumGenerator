import axios from 'axios';
import { Item, Album } from './models';
import { urlencodeSegments } from './utils';

declare const API_ENDPOINT: string;

enum RESOURCES {
    ALBUMS = '/albums',
    ITEMS = '/items',
    ASSETS = '/assets',
};

export enum ASSETS {
    OVERLAY_ALBUM = 'overlay-album',
    OVERLAY_VIDEO = 'overlay-video',
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

export function getAlbum(path: string, onSuccess: (model: Album) => void, onError: (error: string) => void, beforeRequest: () => void = null) {
    if (beforeRequest !== null) {
        beforeRequest();
    }
    let requestCanceller;
    let requestOptions = {
        cancelToken: new axios.CancelToken((c) => requestCanceller = c)
    };
    axios.get(API_ENDPOINT + RESOURCES.ALBUMS + '/' + urlencodeSegments(path), requestOptions).then(
        (response) => onSuccess(<Album>response.data),
        (error) => processError(error, onError),
    );
    return requestCanceller;
}

export function getItem(path: string, onSuccess: (model: Item) => void, onError: (error: string) => void, beforeRequest: () => void = null) {
    if (beforeRequest !== null) {
        beforeRequest();
    }
    let requestCanceller;
    let requestOptions = {
        cancelToken: new axios.CancelToken((c) => requestCanceller = c)
    };
    axios.get(API_ENDPOINT + RESOURCES.ITEMS + '/' + urlencodeSegments(path), requestOptions).then(
        (response) => onSuccess(<Item>response.data),
        (error) => processError(error, onError),
    );
    return requestCanceller;
}

export function getAssetURL(name: string) {
    return API_ENDPOINT + RESOURCES.ASSETS + '/' + name;
}