
declare module 'v-lazy-image' {
    import Vue, { PluginObject } from 'vue'

    export var VLazyImagePlugin: PluginObject<any>
    export default class VLazyImageComponent extends Vue { }
}