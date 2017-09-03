<template>
    <tr v-if="line.collapsed" class="collapsed">
        <td class="block-no" colspan="2">
            <a href="#show"
                tabindex="-1"
                @click="revealTranslated(line)"
            >{{ line.index }} – {{ line.nextLineIndex - 1 }}</a>
        </td>
    </tr>
    <tr v-else valign="top" v-bind:class="{ italic: line.isItalic }">
        <td class="block-no">
            {{ line.index }}
        </td>
        <td>
            <a class="btn-reverso" @click="translateReversoAndOpen(line)">
                <span class="count">{{ line.reversoInfo ? (line.reversoInfo.length || '') : '' }}</span>
                <img src="/img/reverso.png" width="16" height="16" v-bind:class="{
                'fa-spin': line.loadingReverso,
                'disabled': line.disableReversoInfo,
                }" />
            </a>
            <pre class="original" v-html="line.html"></pre>

            <div class="reverso-info" v-if="line.showReversoInfo">

                <p class="source">
                    <a target="reverso"
                        v-bind:href="'http://context.reverso.net/translation/english-russian/' + encodeURIComponent(line.originalFlat)">
                        {{ line.original }}
                    </a>
                </p>

                <span class="fa fa-times close" @click="hideReverso(line)"></span>

                <div v-for="phrase in line.reversoInfo">
                    <div class="row">
                        <div class="col-sm-3">
                            <a class="original"
                                target="reverso"
                                v-bind:href="'http://context.reverso.net/translation/english-russian/' + encodeURIComponent(phrase.source)">
                                {{ phrase.source }}
                            </a>
                        </div>
                        <div class="col-sm-9">
                            <i v-for="target in phrase.target" @click="copyToBuffer($event)">
                                {{ target }}
                            </i>
                        </div>
                    </div>
                </div>
            </div>

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
                v-bind:tabindex="-1"
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
                this.$bus.$emit('userActive');
                let hasTranslation = line.translationYandex.length > 0 && !line.loadingYandex;
                if (!line.approveYandex || !hasTranslation) {
                    vue.set(line, 'approveYandex', hasTranslation);
                    if (hasTranslation) {
                        vue.set(line, 'approveGoogle', false);
                    }
                    this.$emit('edited');
                }
            },
            approveGoogle: function (line) {
                this.$bus.$emit('userActive');
                let hasTranslation = line.translationGoogle.length > 0 && !line.loadingGoogle;
                if (!line.approveGoogle || !hasTranslation) {
                    vue.set(line, 'approveGoogle', hasTranslation);
                    if (hasTranslation) {
                        vue.set(line, 'approveYandex', false);
                    }
                    this.$emit('edited');
                }
            },
            hideReverso: function(line) {
                line.showReversoInfo = false;
                Vue.set(line, 'showReversoInfo', false);
            },
            translateReversoAndOpen: function(line) {
                if (line.reversoInfo) {
                    Vue.set(line, 'showReversoInfo', true);
                    return;
                }
                line.translateReverso(() => {
                    if (line.reversoInfo.length) {
                        Vue.set(line, 'showReversoInfo', true);
                    }
                });
            },
            revealTranslated: function (line) {
                this.$bus.$emit('userActive');
                this.$emit('reveal-clicked', line);
            },
            playPhrase: function (line) {
                window.mediaPlayer.play();
                window.mediaPlayer.seek(line.secondStart);
                this.$bus.$emit('userActive');
            },
            copyToBuffer: function (event) {
                let text = event.target.outerText;
                let textarea = document.createElement("textarea");
                textarea.textContent = text;
                textarea.style.position = "fixed";  // Prevent scrolling to bottom of page in MS Edge.
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    return document.execCommand("copy");  // Security exception may be thrown by some browsers.
                } catch (ex) {
                    console.warn("Copy to clipboard failed.", ex);
                    return false;
                } finally {
                    document.body.removeChild(textarea);
                }
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

    textarea.loading {
        background: #EEE;
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
        bottom: 20px;
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

    .btn-reverso {
        border: none !important;
        cursor: pointer;
        position: absolute;
        right: 10px;
        bottom: 20px;

        .disabled {
            filter: grayscale(100%);
            opacity: 0.4;
        }

        .count {
            position: absolute;
            font-size: 10px;
            color: #333;
            margin-left: 15px;
            margin-top: 14px;
        }
    }

    .reverso-info {
        margin-top: 1em;
        position: absolute;
        z-index: 10;
        width: 700px;
        background: #FFF;
        padding: 1.2em;
        box-shadow: 1px 1px 120px rgba(0,0,0,0.2);
        border-radius: 5px;

        p.source {
            margin-bottom: 1.5em;
        }

        &:hover {
            z-index: 11;
        }

        .row {
            margin-top: 1ex;
        }

        .close {
            right: 10px;
            top: 10px;
            font-size: 30px;
            position: absolute;
        }

        .original {
            display: block;
        }

        i {
            font-style: normal;
            font-size: 13.5px;
            display: inline-block;
            padding: 3px 5px;
            background-color: #eef8ff;
            border: 1px solid #9bbbcd;
            color: #32485f;
            border-radius: 3px;
            margin-right: 1ex;
            margin-bottom: 1ex;
            cursor: pointer;
            text-decoration: none;
        }
    }
</style>
