<template>
    <div id="wagContainer">
        <div class="wagHeader">
            <action-view v-if="backAction !== null" :model="backAction" />
            <div v-if="viewInfo !== null" class="wagCaption">{{viewInfo.caption}}</div>
            <div v-else class="wagCaption">Loading...</div>
            <action-view
                v-for="action in (viewInfo !== null ? viewInfo.actions : [])"
                :key="action.name"
                :model="action"
            />
            <action-view :model="menuAction" />
        </div>
        <router-view :window-size="windowSize" @viewReady="onViewReady"></router-view>
    </div>
</template>

<script lang="ts">
import Component from 'vue-class-component';
// register router hooks
Component.registerHooks([
    'beforeRouteEnter',
    'beforeRouteLeave',
    'beforeRouteUpdate'
]);

import Vue from 'vue';
import { Route } from 'vue-router';
import { PATHS, ROOT_CAPTION, WAG_CONTAINER_ID } from './constants';
import { trailingPath, urldecodeSegments } from './utils';
import { Dim2D, ViewReadyInfo, Action } from './models';
import { getAssetURL, ASSETS } from './service';
import { Prop } from 'vue-property-decorator';
import ActionView from './ActionView.vue';

@Component({
    components: {
        ActionView
    }
})
export default class WAG extends Vue {
    @Prop()
    rootViewReadyHandler: (info: ViewReadyInfo) => void;

    readonly btnMenu = getAssetURL(ASSETS.BTN_MENU);
    readonly btnBack = getAssetURL(ASSETS.BTN_BACK);

    windowSize: Dim2D = null;
    backAction: Action = null;
    menuAction: Action = new Action('Menu', null, null, this.btnMenu, false);
    viewInfo: ViewReadyInfo = null;

    mounted() {
        this.onResize();
        window.addEventListener('resize', this.onResize);
        this.$router.afterEach(this.onPathChange);
        this.updateBackAction(this.$route.path);
    }

    beforeDestroy() {
        window.removeEventListener('resize', this.onResize);
    }

    onPathChange(to: Route, from: Route) {
        this.updateBackAction(to.path);
    }

    private updateBackAction(path: string) {
        let itemPath = null;
        if (path.startsWith(PATHS.ALBUM)) {
            itemPath = trailingPath(PATHS.ALBUM, path);
        } else if (path.startsWith(PATHS.ITEM)) {
            itemPath = trailingPath(PATHS.ITEM, path);
        } else {
            itemPath = 'dummy';
        }

        let sections = itemPath.split('/');
        if (sections.length < 1 || itemPath === '') {
            this.backAction = null;
        } else if (sections.length === 1) {
            this.backAction = new Action(
                'Back to ' + ROOT_CAPTION,
                PATHS.ALBUM,
                null,
                this.btnBack,
                true
            );
        } else {
            sections.pop();
            this.backAction = new Action(
                'Back to ' + urldecodeSegments(sections[sections.length - 1]),
                PATHS.ALBUM + '/' + sections.join('/'),
                null,
                this.btnBack,
                true
            );
        }
    }

    onViewReady(info: ViewReadyInfo) {
        this.viewInfo = info;
        document.title = info.caption;
        this.rootViewReadyHandler(info);
    }

    onResize() {
        this.windowSize = {
            w: window.innerWidth,
            h: window.innerHeight
        };
    }
}
</script>