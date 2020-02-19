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
}

export type Album = {
    albums: AlbumEntry[],
    media: AlbumEntry[],
}

export type Image = {
    path: string,
}

export type VideoEntry = {
    path: string,
    mimeType: string,
}

export type Video = {
    alternatives: VideoEntry[],
    posterPath: string,
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
