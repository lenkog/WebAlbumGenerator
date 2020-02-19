<template>
    <span>
        <div class="wagSlab">
            <router-link
                :to="model.path"
                class="wagLink wagSlabLink"
                :class="{wagSlabAlbumLink: isAlbum}"
            >
                <img
                    :src="model.thumbnailURL"
                    :title="model.caption"
                    :alt="model.caption"
                    class="wagSlabThumbnail"
                    :class="{wagSlabAlbumThumbnail: isAlbum}"
                />
                <img
                    v-if="model.overlayURL !== null"
                    :src="model.overlayURL"
                    :title="model.caption"
                    class="wagSlabOverlay"
                />
            </router-link>
            {{caption}}
        </div>
        {{ ' ' }}
    </span>
</template>

<script lang="ts">
import Component from 'vue-class-component';

import Vue from 'vue';
import { Prop } from 'vue-property-decorator';
import { Slab, ItemType } from './models';

@Component({})
export default class SlabView extends Vue {
    @Prop()
    private model: Slab;
    isAlbum = false;
    caption = '';

    mounted() {
        this.isAlbum = this.model.type === ItemType.ALBUM;
        this.caption = this.isAlbum ? this.model.caption : '';
    }
}
</script>