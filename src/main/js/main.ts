import Vue from 'vue';
import VueRouter from 'vue-router';

import "./../css/main.less";

import {
    PATHS,
} from './constants';
import WAG from './WAG.vue';
import AlbumView from './AlbumView.vue';
import ItemView from './ItemView.vue';
import { ViewReadyInfo } from './models';
import { VLazyImagePlugin } from 'v-lazy-image';

window.onload = function () {
    let pendingScroll: {
        action: () => void,
        complete: () => void,
    } = {
        action: null,
        complete: () => {
            if (pendingScroll.action !== null) {
                pendingScroll.action();
                pendingScroll.action = null;
            }
        }
    };

    Vue.use(VueRouter);
    Vue.use(VLazyImagePlugin);

    const routes = [
        { path: '/', redirect: PATHS.ALBUM + '/' },
        { path: PATHS.ALBUM + '/:path*', component: AlbumView },
        { path: PATHS.ITEM + '/:path*', component: ItemView },
    ]
    const router = new VueRouter({
        routes: routes,
        scrollBehavior(to, from, savedPosition) {
            savedPosition = (savedPosition ? savedPosition : { x: 0, y: 0 });
            pendingScroll.complete();
            return new Promise((resolve, reject) => pendingScroll.action = () => resolve(savedPosition));
        },
    });

    function onViewReady(info: ViewReadyInfo) {
        document.title = info.caption;
        pendingScroll.complete();
    }

    new Vue({
        router,
        el: '#wag',
        render: h => h(WAG, { props: { rootViewReadyHandler: onViewReady } }),
    })
}