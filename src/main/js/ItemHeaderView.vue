<template>
    <div class="wagItemHeader">
        <div v-if="model !== null" class="wagItemCaption">{{model.caption}}</div>
        <div v-else class="wagItemCaption">Loading...</div>
    </div>
</template>

<script lang="ts">
import Component from 'vue-class-component';

import Vue from 'vue';
import { Prop } from 'vue-property-decorator';
import { Item } from './models';

@Component({})
export default class ItemHeaderView extends Vue {
    @Prop()
    private model: Item;
}
</script>