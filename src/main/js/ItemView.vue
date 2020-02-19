<template>
    <div>
        <div class="wagItemHeader">
            <div v-if="model!==null" class="wagItemCaption">{{model.caption}}</div>
            <div v-else class="wagItemCaption">Loading...</div>
        </div>
        <image-view v-if="isImage()" :model="model" :window-size="windowSize" />
        <video-view v-else-if="isVideo()" :model="model" :window-size="windowSize" />
        <album-view v-else-if="isAlbum()" :model="model" />
    </div>
</template>

<script lang="ts">
import Component from 'vue-class-component';

import Vue from 'vue';
import AlbumView from './AlbumView.vue';
import ImageView from './ImageView.vue';
import VideoView from './VideoView.vue';
import { getItem } from './service';
import { PATHS, ROOT_CAPTION } from './constants';
import { Route } from 'vue-router';
import { trailingPath } from './utils';
import { Item, ItemType, Dim2D } from './models';
import { Prop } from 'vue-property-decorator';

@Component({
    components: {
        AlbumView,
        ImageView,
        VideoView
    }
})
export default class ItemView extends Vue {
    @Prop()
    windowSize: Dim2D;

    path: string = '';
    model: Item = null;

    isAlbum() {
        return this.model !== null && this.model.type === ItemType.ALBUM;
    }

    isImage() {
        return this.model !== null && this.model.type === ItemType.IMAGE;
    }

    isVideo() {
        return this.model !== null && this.model.type === ItemType.VIDEO;
    }

    mounted() {
        this.model = null;
        this.path = this.$route.params.path;
        if (typeof this.path === 'undefined') {
            this.path = '';
        }
        getItem(this.path, this.onLoaded, null);
    }

    onLoaded(model: Item) {
        this.model = model;
        if (this.model.type === ItemType.ALBUM && this.path === '') {
            this.model.caption = ROOT_CAPTION;
        }
        this.$emit('title', this.model.caption);
    }

    beforeRouteUpdate(to: Route, from: Route, next: Function) {
        try {
            this.model = null;
            this.path = trailingPath(PATHS.ITEM, to.path);
            getItem(this.path, this.onLoaded, null);
            next();
        } catch (e) {
            console.error(e);
            next(false);
        }
    }
}
</script>