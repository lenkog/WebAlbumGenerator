import axios from 'axios';
import { Item, Album } from './models';

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

export function getAlbum(path: string, onSuccess: (model: Album) => void, onError: (error: string) => void) {
    axios.get(API_ENDPOINT + RESOURCES.ALBUMS + '/' + path).then(
        (response) => onSuccess(<Album>response.data),
        (error) => (onError != null ? onError(error.data) : console.error));
}

export function getItem(path: string, onSuccess: (model: Item) => void, onError: (error: string) => void) {
    axios.get(API_ENDPOINT + RESOURCES.ITEMS + '/' + path).then(
        (response) => onSuccess(<Item>response.data),
        (error) => (onError != null ? onError(error.data) : console.error));
}

export function getAssetURL(name: string) {
    return API_ENDPOINT + RESOURCES.ASSETS + '/' + name;
}