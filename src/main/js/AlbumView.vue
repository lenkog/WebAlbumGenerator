<template>
    <div>
        <item-header-view :model="model" />
        <div id="wagSlabs">
            <slab-view v-for="album in albums" :key="album.path" :model="album" />
            <slab-view v-for="medium in media" :key="medium.path" :model="medium" />
        </div>
    </div>
</template>

<script lang="ts">
import Vue from 'vue';
import Component from 'vue-class-component';
import { Route } from 'vue-router';

import { PATHS, ROOT_CAPTION } from './constants';
import { Slab, Album, ItemType, ViewReadyInfo } from './models';
import SlabView from './SlabView.vue';
import ItemHeaderView from './ItemHeaderView.vue';
import { getAssetURL, ASSETS } from './service';
import { trailingPath, urldecodeSegments, urlencodeSegments } from './utils';
import { getAlbum } from './wag';

@Component({
    components: {
        ItemHeaderView,
        SlabView
    }
})
export default class AlbumView extends Vue {
    path: string = '';
    model: Album = null;
    albums: Slab[] = [];
    media: Slab[] = [];
    private cancelPendingRequest: () => void = null;

    mounted() {
        this.model = null;
        this.albums = [];
        this.media = [];
        this.path = urldecodeSegments(this.$route.params.path);
        if (typeof this.path === 'undefined') {
            this.path = '';
        }
        this.cancelPendingRequest = getAlbum(
            this.path,
            this.onLoaded,
            null,
            this.cancelPendingRequest
        );
    }

    onLoaded(model: Album) {
        this.model = model;
        this.albums = [];
        this.media = [];
        this.model.albums.forEach(info =>
            this.albums.push(
                new Slab(
                    info.type,
                    info.caption,
                    urlencodeSegments(PATHS.ALBUM + '/' + info.path),
                    info.thumbnail,
                    getAssetURL(ASSETS.OVERLAY_ALBUM)
                )
            )
        );
        this.model.media.forEach(info =>
            this.media.push(
                new Slab(
                    info.type,
                    info.caption,
                    urlencodeSegments(PATHS.ITEM + '/' + info.path),
                    info.thumbnail,
                    info.type === ItemType.VIDEO
                        ? getAssetURL(ASSETS.OVERLAY_VIDEO)
                        : null
                )
            )
        );
        this.$emit('viewReady', new ViewReadyInfo(this.model.caption));
    }

    beforeRouteUpdate(to: Route, from: Route, next: Function) {
        try {
            this.model = null;
            this.albums = [];
            this.media = [];
            this.path = trailingPath(PATHS.ALBUM, urldecodeSegments(to.path));
            this.cancelPendingRequest = getAlbum(
                this.path,
                this.onLoaded,
                null,
                this.cancelPendingRequest
            );
            next();
        } catch (e) {
            console.error(e);
            next(false);
        }
    }

    beforeDestroy() {
        this.cancelPendingRequest();
    }
}
</script>