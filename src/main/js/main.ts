import Vue from 'vue';
import VueRouter from 'vue-router';

import "./../css/main.less";

import {
    PATHS,
} from './constants';
import WAG from './WAG.vue';
import AlbumView from './AlbumView.vue';
import ItemView from './ItemView.vue';

window.onload = function () {
    Vue.use(VueRouter);
    const routes = [
        { path: '/', redirect: PATHS.ALBUM + '/' },
        { path: PATHS.ALBUM + '/:path*', component: AlbumView },
        { path: PATHS.ITEM + '/:path*', component: ItemView },
    ]
    const router = new VueRouter({
        routes: routes,
    });

    new Vue({
        router,
        el: '#wag',
        render: h => h(WAG),
    })
}