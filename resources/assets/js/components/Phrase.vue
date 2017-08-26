<template>
    <tr v-if="line.collapsed" class="collapsed">
        <td class="block-no" colspan="2">
            <a href="#show" @click="revealTranslated(line)">{{ line.index }} – {{ line.nextLineIndex - 1 }}</a>
        </td>
    </tr>
    <tr v-else valign="top" v-bind:class="{ italic: line.isItalic }">
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
            revealTranslated: function (line) {
                this.$emit('reveal-clicked', line);
            },
            playPhrase: function (line) {
                window.mediaPlayer.play();
                window.mediaPlayer.seek(line.secondStart);
            },
        },
    };

</script>

<style lang="scss" scoped>

    a {
        border-bottom: 1px dotted;

        &:hover {
            text-decoration: none;
            border-bottom: 1px solid;
        }
    }

    tr.italic {
        pre.original, textarea {
            font-style: italic;
        }
        pre.original {
            &, a {
                color: #777;
            }
        }
    }

    td {
        padding: 10px;
        position: relative;
    }

    tr.collapsed td {
        color: #AAA;
        padding-bottom: 150px;

        a {
            color: #AAA;
        }
    }

    textarea {
        width: 300px;
    }

    pre {
        font-family: 'Ubuntu Mono', monospace;
        font-size: 15px;
        line-height: 1.4;
        border-radius: 0;
    }

    pre.original,
    textarea {
        height: 75px;
    }

    pre.original {
        padding: 0;
        width: 340px;
        background: transparent;
        border: none;
    }

    textarea {
        @extend pre;
        border-color: rgb(204, 204, 204);
        word-wrap: break-word;
        padding: 0.5ex;
        width: 21.7em;
        &:hover {
            white-space: nowrap;
        }
    }

    textarea:not(.loading) {
        background: #FFF;
        &.approved {
            background: #e6ffdf;
        }
    }

    .chars-left {
        position: absolute;
        bottom: 24px;
        font-size: 11px;
        right: 37px;
        text-align: right;
        opacity: 0.5;
    }

    .too-long {
        textarea:not(.loading) {
            &.approved {
                background: #fff1f1;
            }
        }

        .chars-left {
            background: red;
            color: #FFF;
            padding: 0 .8ex;
            border-radius: 10px;
            opacity: 1;
        }
    }

    .btn {
        position: absolute;
        right: 11px;
        bottom: 21px;
        padding-right: 1ex;
    }

    span.enter {
        display: none;
        &::after {
            content: "↵";
            color: #AAA;
            font-size: 16px;
            line-height: 1;
            margin-left: 3px;
        }
    }

    .btn.btn-play-phrase {
        position: absolute;
        left: 0;
        top: 10px;
        padding: 5px;
    }

    .btn.btn-play-phrase + textarea {
        margin-left: 12px;
    }

    .block-no {
        font-size: 12px;
        padding-top: 12px;
    }

</style>
