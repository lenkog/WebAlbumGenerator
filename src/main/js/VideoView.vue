<template>
    <div>
        <video
            ref="video"
            :style="'maxWidth:' + maxDim.w + 'px; maxHeight:' + maxDim.h + 'px;'"
            class="wagVideo"
            controls="controls"
            preload="none"
            :poster="getPosterURL()"
        >
            <source
                v-for="alternative in model.alternatives"
                :key="alternative.path"
                :src="getVideoURL(alternative.path)"
                :type="alternative.mimeType"
            />
            <div class="wagErrorMsg">This browser does not support the playback of HTML5 videos.</div>
            <a
                :href="getVideoURL(model.alternatives[0].path)"
                class="wagLink"
            >Click here to download the video.</a>
        </video>
    </div>
</template>

<script lang="ts">
import Component from 'vue-class-component';

import Vue from 'vue';
import { Prop } from 'vue-property-decorator';
import { Dim2D, Video } from './models';
import { getAvailableArea } from './utils';
import { getMediumURL } from './service';

@Component({})
export default class VideoView extends Vue {
    @Prop()
    private model: Video;
    @Prop()
    windowSize: Dim2D;

    maxDim: Dim2D = {
        w: 1024,
        h: 1024
    };

    $refs: {
        video: Element;
    };

    getPosterURL() {
        console.log(this.model);
        return this.model.posterPath != null
            ? getMediumURL(this.model.posterPath)
            : null;
    }

    getVideoURL(path: string) {
        return getMediumURL(path);
    }

    mounted() {
        this.onResize();
        this.$watch('windowSize', this.onResize);
    }

    onResize() {
        this.maxDim = getAvailableArea(this.$refs.video);
    }
}
</script>