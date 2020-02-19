<template>
    <div class="wagContainer">
        <div class="wagBreadcrumbs">
            <span v-if="breadcrumbs.length > 0">Back to</span>
            <div
                v-for="(breadcrumb, index) in breadcrumbs"
                :key="breadcrumb.path"
                class="wagBreadcrumb"
            >
                <span v-if="index > 0" class="wagSpaceOnLeft">:</span>
                <router-link :to="breadcrumb.path">{{breadcrumb.caption}}</router-link>
            </div>
        </div>
        <router-view :window-size="windowSize" @title="updateTitle"></router-view>
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
import { PATHS, ROOT_CAPTION } from './constants';
import { trailingPath } from './utils';
import { Breadcrumb, Dim2D } from './models';

@Component({})
export default class WAG extends Vue {
    windowSize: Dim2D = null;
    breadcrumbs: Breadcrumb[] = [];

    mounted() {
        this.updateTitle(ROOT_CAPTION);
        this.onResize();
        window.addEventListener('resize', this.onResize);
        this.$router.afterEach(this.onPathChange);
        this.updateBreadcrumbs(this.$route.path);
    }

    beforeDestroy() {
        window.removeEventListener('resize', this.onResize);
    }

    updateTitle(title: string) {
        document.title = title;
    }

    onPathChange(to: Route, from: Route) {
        this.updateBreadcrumbs(to.path);
    }

    private updateBreadcrumbs(path: string) {
        this.breadcrumbs = [];

        let itemPath = null;
        if (path.startsWith(PATHS.ITEM)) {
            itemPath = trailingPath(PATHS.ITEM, path);
        } else {
            this.breadcrumbs.push(new Breadcrumb(ROOT_CAPTION, PATHS.ITEM));
            return;
        }

        let sections = itemPath.split('/');
        if (sections.length > 1 || itemPath !== '') {
            this.breadcrumbs.push(new Breadcrumb(ROOT_CAPTION, PATHS.ITEM));
        }
        sections.pop();
        for (let i = 0; i < sections.length; ++i) {
            this.breadcrumbs.push(
                new Breadcrumb(
                    sections[i],
                    PATHS.ITEM + '/' + sections.slice(0, i + 1).join('/')
                )
            );
        }
    }

    onResize() {
        this.windowSize = {
            w: window.innerWidth,
            h: window.innerHeight
        };
    }
}
</script>