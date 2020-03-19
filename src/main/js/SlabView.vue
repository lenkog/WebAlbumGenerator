<template>
    <span>
        <div class="wagSlab">
            <router-link :to="model.path" class="wagLink wagSlabLink">
                <v-lazy-image
                    ref="thumbnail"
                    :src="model.thumbnailURL"
                    :title="model.caption"
                    :alt="model.caption"
                    class="wagSlabThumbnail"
                    @intersect="beforeThumbnailLoad"
                />
                <img
                    v-if="model.overlayURL !== null"
                    :src="model.overlayURL"
                    :title="model.caption"
                    class="wagSlabOverlay"
                />
            </router-link>
            {{slabTitle}}
        </div>
        {{ ' ' }}
    </span>
</template>

<script lang="ts">
import Component from 'vue-class-component';

import Vue from 'vue';
import { Prop } from 'vue-property-decorator';
import { Slab, ItemType } from './models';
import { getAssetURL, ASSETS } from './service';

@Component({})
export default class SlabView extends Vue {
    @Prop()
    private model: Slab;

    slabTitle = '';

    mounted() {
        this.slabTitle =
            this.model.type === ItemType.ALBUM ? this.model.caption : '';
    }

    beforeThumbnailLoad() {
        (<HTMLImageElement>(
            (<Vue>this.$refs.thumbnail).$el
        )).onerror = this.setDefaultThumbnail;
    }

    private setDefaultThumbnail(e: Event) {
        let thumbnail = <HTMLImageElement>e.target;
        if (thumbnail.src !== getAssetURL(ASSETS.DEFAULT_THUMBNAIL)) {
            thumbnail.src = getAssetURL(ASSETS.DEFAULT_THUMBNAIL);
        }
    }
}
</script>