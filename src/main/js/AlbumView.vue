<template>
    <div>
        <div id="wagSlabs">
            <slab-view v-for="album in albums" :key="album.path" :model="album" />
            <slab-view v-for="medium in media" :key="medium.path" :model="medium" />
        </div>
    </div>
</template>

<script lang="ts">
import Vue from 'vue';
import Component from 'vue-class-component';
import { Prop } from 'vue-property-decorator';
import { Route } from 'vue-router';

import { PATHS } from './constants';
import { Slab, Album, ItemType } from './models';
import SlabView from './SlabView.vue';
import { getAssetURL, getThumbnailURL, ASSETS } from './service';

@Component({
    components: {
        SlabView
    }
})
export default class AlbumView extends Vue {
    @Prop()
    model: Album;
    albums: Slab[] = [];
    media: Slab[] = [];

    mounted() {
        this.albums = [];
        this.media = [];
        this.model.albums.forEach(info =>
            this.albums.push(
                new Slab(
                    info.type,
                    info.caption,
                    PATHS.ITEM + '/' + info.path,
                    getThumbnailURL(info.path),
                    getAssetURL(ASSETS.OVERLAY_ALBUM)
                )
            )
        );
        this.model.media.forEach(info =>
            this.media.push(
                new Slab(
                    info.type,
                    info.caption,
                    PATHS.ITEM + '/' + info.path,
                    getThumbnailURL(info.path),
                    info.type === ItemType.VIDEO
                        ? getAssetURL(ASSETS.OVERLAY_VIDEO)
                        : null
                )
            )
        );
    }
}
</script>