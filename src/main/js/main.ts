import Vue from 'vue';
import VueRouter from 'vue-router';

import "./../css/main.less";

import {
    PATHS,
} from './constants';
import WAG from './WAG.vue';
import ItemView from './ItemView.vue';

window.onload = function () {
    Vue.use(VueRouter);
    const routes = [
        { path: '/', redirect: PATHS.ITEM + '/' },
        { path: PATHS.ITEM + '/:path*', component: ItemView },
    ]
    const router = new VueRouter({
        //mode: 'history',
        routes: routes,
    });

    new Vue({
        router,
        el: '#wag',
        render: h => h(WAG),
    })
}