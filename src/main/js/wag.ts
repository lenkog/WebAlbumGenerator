import { META_CAPTION, META_ITEMS, ROOT_CAPTION } from './constants';
import { Album, AlbumEntry, AlbumListing, Image, Item, ItemType, ListingEntryType, MetaData, MetaItems, Video, VideoEntry } from './models';
import { getAlbumListing, getContent } from './service';
import { basename, dirname, filename, getMediaURL, getMetaId, getMetaURL, getThumbnailURL, guessMediaType, videoMIME } from './utils';

class ItemGrouping {
    readonly [ItemType.ALBUM] = <string[]>[];
    readonly [ItemType.IMAGE] = <string[]>[];
    readonly [ItemType.VIDEO] = <string[]>[];
}

function albumFromListing(path: string, listing: AlbumListing, meta: MetaData = null): Album {
    let itemsMeta: MetaItems = {};
    let albumCaption = basename(path);
    if (meta) {
        if (META_CAPTION in meta) {
            albumCaption = meta[META_CAPTION];
        }
        if (META_ITEMS in meta) {
            itemsMeta = meta[META_ITEMS];
        }
    }
    if (path === '') {
        albumCaption = ROOT_CAPTION;
    }
    let album = new Album(albumCaption);
    let groups = new Map<string, ItemGrouping>();
    for (let entry of listing.entries) {
        let key = filename(entry.path);
        if (!groups.has(key)) {
            groups.set(key, new ItemGrouping());
        }
        switch (entry.type) {
            case ListingEntryType.ALBUM:
                groups.get(key)[ItemType.ALBUM].push(entry.path);
                break;
            case ListingEntryType.MEDIUM:
                let type = guessMediaType(entry.path);
                if (type !== null) {
                    groups.get(key)[type].push(entry.path);
                } else {
                    console.error('Unrecognized entry type', entry.path);
                }
                break;
            default:
                console.error('Unrecognized entry type', entry.path);
        }
    }
    groups.forEach(group => {
        for (let entry of group[ItemType.ALBUM]) {
            let itemId = getMetaId(entry);
            let caption = itemId in itemsMeta && META_CAPTION in itemsMeta[itemId] ?
                itemsMeta[itemId][META_CAPTION] : basename(entry);
            album.albums.push(new AlbumEntry(ItemType.ALBUM, caption, entry, getThumbnailURL(listing.mediaURL, entry)));
        }
        if (group[ItemType.VIDEO].length > 0) {
            let entry = group[ItemType.VIDEO][0];
            let itemId = getMetaId(entry);
            let caption = itemId in itemsMeta && META_CAPTION in itemsMeta[itemId] ?
                itemsMeta[itemId][META_CAPTION] : basename(entry);
            album.media.push(new AlbumEntry(ItemType.VIDEO, caption, entry, getThumbnailURL(listing.mediaURL, entry)));
        } else {
            for (let entry of group[ItemType.IMAGE]) {
                let itemId = getMetaId(entry);
                let caption = itemId in itemsMeta && META_CAPTION in itemsMeta[itemId] ?
                    itemsMeta[itemId][META_CAPTION] : basename(entry);
                album.media.push(new AlbumEntry(ItemType.IMAGE, caption, entry, getThumbnailURL(listing.mediaURL, entry)));
            }
        }
    });
    return album;
}

function itemFromListing(path: string, listing: AlbumListing, meta: MetaData = null): Item {
    let itemCaption = basename(path);
    if (meta) {
        if (META_CAPTION in meta) {
            itemCaption = meta[META_CAPTION];
        }
    }
    let itemName = filename(path);
    let group = new ItemGrouping();
    for (let entry of listing.entries) {
        if (entry.type === ListingEntryType.MEDIUM && filename(entry.path) === itemName) {
            let type = guessMediaType(entry.path);
            if (type !== null) {
                group[type].push(entry.path);
            } else {
                console.error('Unrecognized entry type', entry.path);
            }
        }
    }
    if (group[ItemType.VIDEO].length > 0) {
        let poster = group[ItemType.IMAGE].length > 0 ? getMediaURL(listing.mediaURL, group[ItemType.IMAGE][0]) : null;
        let item = new Video(itemCaption, poster);
        group[ItemType.VIDEO].forEach(video => {
            item.alternatives.push(new VideoEntry(getMediaURL(listing.mediaURL, video), videoMIME(video)));
        });
        return item;
    } else {
        let item = new Image(itemCaption, getMediaURL(listing.mediaURL, path));
        return item;
    }
}

export function getAlbum(path: string, onSuccess: (model: Album) => void, onError: (error: string) => void = null, beforeRequest: () => void = null) {
    if (beforeRequest !== null) {
        beforeRequest();
    }
    let cancelWrapper = {
        cancelFunc: () => { }
    };
    cancelWrapper.cancelFunc = getAlbumListing(path, (listing) => {
        cancelWrapper.cancelFunc = getContent(getMetaURL(listing.mediaURL, path), (meta) => {
            onSuccess(albumFromListing(path, listing, meta));
        }, () => {
            onSuccess(albumFromListing(path, listing));
        });
    }, onError);
    return () => cancelWrapper.cancelFunc();
}

export function getItem(path: string, onSuccess: (model: Item) => void, onError: (error: string) => void = null, beforeRequest: () => void = null) {
    if (beforeRequest !== null) {
        beforeRequest();
    }
    let cancelWrapper = {
        cancelFunc: () => { }
    };
    cancelWrapper.cancelFunc = getAlbumListing(dirname(path), (listing) => {
        cancelWrapper.cancelFunc = getContent(getMetaURL(listing.mediaURL, path), (meta) => {
            onSuccess(itemFromListing(path, listing, meta));
        }, () => {
            onSuccess(itemFromListing(path, listing));
        });
    }, onError);
    return () => cancelWrapper.cancelFunc();
}
