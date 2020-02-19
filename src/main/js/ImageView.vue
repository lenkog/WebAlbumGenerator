<template>
    <div>
        <img
            ref="image"
            :src="getImageURL()"
            :style="'maxWidth:' + maxDim.w + 'px; maxHeight:' + maxDim.h + 'px;'"
            class="wagImage"
        />
    </div>
</template>

<script lang="ts">
import Component from 'vue-class-component';

import Vue from 'vue';
import { Prop } from 'vue-property-decorator';
import { Image, Dim2D } from './models';
import { getAvailableArea } from './utils';
import { getMediumURL } from './service';

@Component({})
export default class ImageView extends Vue {
    @Prop()
    private model: Image;
    @Prop()
    windowSize: Dim2D;

    maxDim: Dim2D = {
        w: 1024,
        h: 1024
    };

    $refs: {
        image: Element;
    };

    getImageURL() {
        return getMediumURL(this.model.path);
    }

    mounted() {
        this.onResize();
        this.$watch('windowSize', this.onResize);
    }

    onResize() {
        this.maxDim = getAvailableArea(this.$refs.image);
    }
}
</script>