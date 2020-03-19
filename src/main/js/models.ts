import { META_APERTURE, META_CAPTION, META_COPYRIGHT, META_DATE, META_HEIGHT, META_ISO, META_ITEMS, META_LAT, META_LON, META_SHUTTER, META_WIDTH, META_ZOOM } from './constants';

export type Dim2D = {
    w: number,
    h: number,
}

export class ViewReadyInfo {
    constructor(
        readonly caption: string,
    ) { }
}

export enum ListingEntryType {
    ALBUM = 'album',
    MEDIUM = 'medium',
}

export type ListingEntry = {
    type: ListingEntryType,
    path: string,
}

export type AlbumListing = {
    mediaURL: string,
    entries: ListingEntry[],
}

export type MetaItems = {
    [key: string]: {
        [META_CAPTION]?: string,
        [META_DATE]?: string,
    }
}

export type MetaData = {
    [META_APERTURE]?: number,
    [META_CAPTION]?: string,
    [META_COPYRIGHT]?: string,
    [META_DATE]?: string,
    [META_HEIGHT]?: number,
    [META_ISO]?: number,
    [META_ITEMS]?: MetaItems,
    [META_LAT]?: number,
    [META_LON]?: number,
    [META_SHUTTER]?: string,
    [META_WIDTH]?: number,
    [META_ZOOM]?: number,
}

export enum ItemType {
    ALBUM = 'album',
    IMAGE = 'image',
    VIDEO = 'video',
}

export class Item {
    constructor(
        readonly type: ItemType,
        readonly caption: string,
    ) { }
}

export class AlbumEntry {
    constructor(
        readonly type: ItemType,
        readonly caption: string,
        readonly path: string,
        readonly thumbnail: string,
    ) { }
}

export class Album extends Item {
    constructor(caption: string) {
        super(ItemType.ALBUM, caption);
    }
    readonly albums: AlbumEntry[] = [];
    readonly media: AlbumEntry[] = [];
}

export class Image extends Item {
    constructor(caption: string, readonly url: string) {
        super(ItemType.IMAGE, caption);
    }
}

export class VideoEntry {
    constructor(readonly url: string, readonly mimeType: string) { }
}

export class Video extends Item {
    constructor(caption: string, readonly posterURL: string) {
        super(ItemType.VIDEO, caption);
    }
    readonly alternatives: VideoEntry[] = [];
}

export class Breadcrumb {
    constructor(
        readonly caption: string,
        readonly path: string = '',
    ) { }
}

export class Slab {
    constructor(
        readonly type: ItemType,
        readonly caption: string,
        readonly path: string = '',
        readonly thumbnailURL: string = '',
        readonly overlayURL: string = null,
    ) { }
}
