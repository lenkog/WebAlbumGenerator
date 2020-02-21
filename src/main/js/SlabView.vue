<template>
    <span>
        <div class="wagSlab">
            <router-link :to="model.path" class="wagLink wagSlabLink">
                <img
                    :src="model.thumbnailURL"
                    :title="model.caption"
                    :alt="model.caption"
                    class="wagSlabThumbnail"
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

@Component({})
export default class SlabView extends Vue {
    @Prop()
    private model: Slab;
    slabTitle = '';

    mounted() {
        this.slabTitle =
            this.model.type === ItemType.ALBUM ? this.model.caption : '';
    }
}
</script>