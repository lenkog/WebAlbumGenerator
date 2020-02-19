import axios from 'axios';
import { Item } from './models';

declare const API_ENDPOINT: string;

enum RESOURCES {
    ITEM = '/item',
    MEDIA = '/media',
    THUMBNAILS = '/thumbnails',
    ASSETS = '/assets',
};

export enum ASSETS {
    OVERLAY_ALBUM = 'overlay-album',
    OVERLAY_VIDEO = 'overlay-video',
}

export function getItem(path: string, onSuccess: (model: Item) => void, onError: (error: string) => void) {
    axios.get(API_ENDPOINT + RESOURCES.ITEM + '/' + path).then(
        (response) => onSuccess(<Item>response.data),
        (error) => (onError != null ? onError(error.data) : console.error));
}

export function getMediumURL(path: string) {
    return API_ENDPOINT + RESOURCES.MEDIA + '/' + path;
}

export function getThumbnailURL(path: string) {
    return API_ENDPOINT + RESOURCES.THUMBNAILS + '/' + path;
}

export function getAssetURL(name: string) {
    return API_ENDPOINT + RESOURCES.ASSETS + '/' + name;
}