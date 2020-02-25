<template>
    <div>
        <item-header-view :model="model" />
        <image-view v-if="isImage()" :model="model" :window-size="windowSize" />
        <video-view v-else-if="isVideo()" :model="model" :window-size="windowSize" />
    </div>
</template>

<script lang="ts">
import Component from 'vue-class-component';

import Vue from 'vue';
import ItemHeaderView from './ItemHeaderView.vue';
import ImageView from './ImageView.vue';
import VideoView from './VideoView.vue';
import { getAlbum, getItem } from './service';
import { PATHS, ROOT_CAPTION } from './constants';
import { Route } from 'vue-router';
import { trailingPath } from './utils';
import { Item, ItemType, Dim2D } from './models';
import { Prop } from 'vue-property-decorator';

@Component({
    components: {
        ItemHeaderView,
        ImageView,
        VideoView
    }
})
export default class ItemView extends Vue {
    @Prop()
    windowSize: Dim2D;

    path: string = '';
    model: Item = null;

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