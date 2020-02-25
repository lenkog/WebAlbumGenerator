export type Dim2D = {
    w: number,
    h: number,
}

export enum ItemType {
    ALBUM = 'album',
    IMAGE = 'image',
    VIDEO = 'video',
}

export type Item = {
    type: ItemType,
    caption: string,
}

export type AlbumEntry = {
    type: ItemType,
    caption: string,
    path: string,
    thumbnail: string,
}

export type Album = Item & {
    albums: AlbumEntry[],
    media: AlbumEntry[],
}

export type Image = Item & {
    url: string,
}

export type VideoEntry = {
    url: string,
    mimeType: string,
}

export type Video = Item & {
    alternatives: VideoEntry[],
    posterURL: string,
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
