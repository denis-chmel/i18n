<template>
    <tr valign="top" v-bind:class="{ italic: line.isItalic }">
        <td class="block-no">
            {{ line.index }}
        </td>
        <td>
            <pre class="original" v-html="line.html"></pre>
        </td>
        <td v-if="line.editable && inViewport.now"
            v-bind:class="{
                'too-long': 0 > getCharsLeft(line.translationGoogle, line.chars)
            }">

            <button tabindex="-1" class="btn btn-default btn-xs" type="button" @click="line.translateGoogle()">
                G
            </button>

            <div class="chars-left">{{ getCharsLeft(line.translationGoogle, line.chars) }}</div>

            <button type="button"
                class="btn btn-default btn-xs btn-play-phrase"
                tabindex="-1"
                @click="playPhrase(line)"
            >►
            </button>

            <textarea
                class="google"
                v-bind:tabindex="line.approveYandex ? -1 : null"
                :disabled="line.disabled == true"
                @focus="focusGoogle(line)"
                @keyup="approveGoogle(line)"
                v-bind:class="{
                    loading: line.loadingGoogle,
                    approved: line.approveGoogle,
                }"
                v-model="line.translationGoogle"
            ></textarea>
        </td>
        <td v-if="line.editable && inViewport.now"
            v-bind:class="{
                        'too-long': 0 > getCharsLeft(line.translationYandex, line.chars)
                    }">
            <button tabindex="-1" class="btn btn-default btn-xs" type="button" @click="line.translateYandex()">
                Я
            </button>

            <div class="chars-left">{{ getCharsLeft(line.translationYandex, line.chars) }}</div>

            <textarea
                class="yandex"
                v-bind:tabindex="line.approveGoogle ? -1 : null"
                :disabled="line.disabled == true"
                @focus="focusYandex(line)"
                @keyup="approveYandex(line)"
                v-bind:class="{
                    loading: line.loadingYandex,
                    approved: line.approveYandex,
                }"
                v-model="line.translationYandex"
            ></textarea>
        </td>
    </tr>

</template>

<script>

    inViewport = require('vue-in-viewport-mixin');
    module.exports = {
        props: ['line'],
        mixins: [inViewport],
        watch: {
            'inViewport.now': function (visible) {
                // console.log('This component is ' + ( visible ? 'in-viewport' : 'hidden'));
            }
        },
        methods: {
            getCharsLeft: function (text, limit) {
                return limit - text.split("\n").join('').length;
            },
            seekLineInPlayer: function (line) {
                if (window.mediaPlayer.isPaused()) {
                    window.mediaPlayer.seek(line.secondStart);
                } else {
                    window.mediaPlayer.pause();
                }
            },
            focusYandex: function (line) {
                this.seekLineInPlayer(line);
                this.approveYandex(line);
            },
            focusGoogle: function (line) {
                this.seekLineInPlayer(line);
                this.approveGoogle(line);
            },
            approveYandex: function (line) {
                let hasTranslation = line.translationYandex.length > 0 && !line.loadingYandex;
                if (!line.approveYandex || !hasTranslation) {
                    Vue.set(line, 'approveYandex', hasTranslation);
                    if (hasTranslation) {
                        Vue.set(line, 'approveGoogle', false);
                    }
                    this.$emit('edited');
                }
            },
            approveGoogle: function (line) {
                let hasTranslation = line.translationGoogle.length > 0 && !line.loadingGoogle;
                if (!line.approveGoogle || !hasTranslation) {
                    Vue.set(line, 'approveGoogle', hasTranslation);
                    if (hasTranslation) {
                        Vue.set(line, 'approveYandex', false);
                    }
                    this.$emit('edited');
                }
            },
        },
    };

</script>
