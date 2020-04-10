<template>
    <div class="wagAction">
        <img v-if="!model.enabled" class="wagBtnDisabled" :src="model.icon" />
        <router-link v-else-if="model.link" :to="model.link">
            <img
                class="wagBtn"
                :src="model.icon"
                :alt="model.name"
                :title="model.name"
                @click="onClick"
            />
        </router-link>
        <img
            v-else
            class="wagBtn"
            :src="model.icon"
            :alt="model.name"
            :title="model.name"
            @click="onClick"
        />
    </div>
</template>

<script lang="ts">
import Component from 'vue-class-component';

import Vue from 'vue';
import { Prop } from 'vue-property-decorator';
import { Action } from './models';

@Component({})
export default class ActionView extends Vue {
    @Prop()
    private model: Action;

    onClick() {
        if (this.model.call) {
            this.model.call();
        }
    }
}
</script>