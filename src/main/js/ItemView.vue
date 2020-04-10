<template>
    <div>
        <image-view v-if="isImage()" :model="model" :window-size="windowSize" />
        <video-view v-else-if="isVideo()" :model="model" :window-size="windowSize" />
    </div>
</template>

<script lang="ts">
import Component from 'vue-class-component';

import Vue from 'vue';
import ImageView from './ImageView.vue';
import VideoView from './VideoView.vue';
import { getItem } from './wag';
import { PATHS, ROOT_CAPTION } from './constants';
import { Route } from 'vue-router';
import { trailingPath, urldecodeSegments } from './utils';
import { Item, ItemType, Dim2D, ViewReadyInfo, Action } from './models';
import { Prop } from 'vue-property-decorator';
import { getAssetURL, ASSETS } from './service';

@Component({
    components: {
        ImageView,
        VideoView
    }
})
export default class ItemView extends Vue {
    @Prop()
    windowSize: Dim2D;

    path: string = '';
    model: Item = null;
    private cancelPendingRequest: () => void = null;

    isImage() {
        return this.model?.type === ItemType.IMAGE;
    }

    isVideo() {
        return this.model?.type === ItemType.VIDEO;
    }

    mounted() {
        this.model = null;
        this.path = urldecodeSegments(this.$route.params.path);
        if (typeof this.path === 'undefined') {
            this.path = '';
        }
        this.cancelPendingRequest = getItem(
            this.path,
            this.onLoaded,
            null,
            this.cancelPendingRequest
        );
    }

    onLoaded(model: Item) {
        this.model = model;
        let actions = [
            new Action(
                'Previous',
                this.model.navigation?.prev,
                null,
                getAssetURL(ASSETS.BTN_PREV),
                !!this.model.navigation?.prev
            ),
            new Action(
                'Next',
                this.model.navigation?.next,
                null,
                getAssetURL(ASSETS.BTN_NEXT),
                !!this.model.navigation?.next
            )
        ];
        this.$emit('viewReady', new ViewReadyInfo(this.model.caption, actions));
    }

    beforeRouteUpdate(to: Route, from: Route, next: Function) {
        try {
            this.model = null;
            this.path = trailingPath(PATHS.ITEM, urldecodeSegments(to.path));
            this.cancelPendingRequest = getItem(
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