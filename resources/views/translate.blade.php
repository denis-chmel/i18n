@php #
/**
 * @var array $lines
 * @var array $bannedWords
 * @var string $sessionToken
 * @var int $jobId
 * @var boolean $isDebug
 * @var int $untranslatedCount
 */

if ($no = request('box')) {
    $lines = array_slice($lines, $no - 1, 1);
}
// $lines = array_slice($lines, 0, 20);

@endphp

@extends('layout')

@section('contents')

    <div id="app"
        v-cloak
        xmlns:v-bind="http://www.w3.org/1999/xhtml"
        xmlns:v-on="http://www.w3.org/1999/xhtml"
    >

        <navbar
            v-bind:sub-lines="subLines"
            v-bind:percent-done="percentDone"
            v-bind:translated-count="translatedCount"
            v-bind:job-id="jobId"
            v-bind:is-debug="{{ (int)$isDebug }}"
        ></navbar>

        <video data-dashjs-player id="mediaPlayer" controls></video>

        <div class="container">
            <table class="translations">
                <tbody>
                <tr is="phrase"
                    v-for="line in nonCollapsedLines" :key="line.index"
                    :in-viewport-offset-top='1000'
                    :in-viewport-active='viewPortActive'
                    v-bind:line="line"
                    v-on:edited="calculatePercentDone"
                    v-on:reveal-clicked="revealLines"
                ></tr>
                </tbody>
            </table>

            <footer>
                <p>© 2001-2017, Denis feat. Love</p>
            </footer>

        </div>
    </div>

@endsection

@section('footer-scripts')
    <script type="text/javascript">

        let bannedWords = {!! json_encode($bannedWords) !!};

        function addExtraMethods(vue, line) {
            line.hasTranslations = function () {
                return (line.translationYandex.length + line.translationGoogle.length > 0);
            };

            line.translateGoogle = function (callback) {
                let line = this;
                if (!line.original.length) {
                    return;
                }
                Vue.set(line, 'loadingGoogle', true);
                Vue.set(line, 'translationGoogle', line.original);

                let original = encodeURI(line.original);
                vue.$http.get('/translate-google?from=en&to=ru&text=' + original).then((response) => {
                    line.translationGoogle = response.body.translation;
                    line.loadingGoogle = false;
                    if (callback) callback();
                });
            };

            line.translateYandex = function (callback) {
                let line = this;
                if (!line.original.length) {
                    return;
                }
                Vue.set(line, 'loadingYandex', true);
                Vue.set(line, 'translationYandex', line.original);
                Vue.set(vue.subLines, 'reload', Math.random());
                window.translateYandex.translate(line.original, { to: 'ru' }, function (err, res) {
                    line.translationYandex = res.text[0];
                    line.loadingYandex = false;
                    if (callback) callback();
                });
            };
        }

        function addLinksToMultitran(line) {

            let words = line.original.match(/\S+/g) || [];

            words.forEach((word, index) => {
                words[index] = word.trimChars('.,-!?:');
            });

            let unique = Array.from(new Set(words));

            unique = unique.filter(word => {
                if (word.length < 3) {
                    return;
                }
                if (word.startsWithAny([
                        'you\'', 'it\'', 'can\'', 'we\'', 'haven\''
                    ])) {
                    return;
                }
                return true;
            });

            let html = line.original;

            unique.sort(function (a, b) {
                // ASC  -> a.length - b.length
                // DESC -> b.length - a.length
                return a.length - b.length;
            });

            let target = '{{ uniqid() }}';
            unique.forEach((word) => {
                let singular = window.pluralize.singular(word);
                if (bannedWords.includes(singular.toLowerCase())) {
                    return;
                }
                let regex = new RegExp('\\b' + word + '\\b');
                html = html.replace(regex, `<a target="${target}" tabindex="-1" href="https://www.multitran.ru/c/m.exe?s=${encodeURIComponent(singular)}">${word}</a>`);
            });
            line.html = html.replace(/\n/, '<span class="enter"></span>\n');
        }

        const app = new Vue({
            el: '#app',
            data: {
                viewPortActive: true,
                percentDone: 0,
                jobId: {{ $jobId }},
                isDebug: {{ (int)$isDebug }},
                videoUrl: {!! j($videoUrl) !!},
                subLines: {!! j($lines) !!},
            },
            computed: {
                translatedCount: function() {
                    let translated = this.subLines.filter(line => {
                        return line.approveYandex || line.approveGoogle ? line : false;
                    });
                    return translated.length;
                },
                nonCollapsedLines: function () {
                    let result = [];
                    let prevLine = null;
                    this.subLines.forEach(function (line) {
                        if (line.collapsed) {
                            if (!prevLine || !prevLine.collapsed) {
                                result.push(line);
                            }
                        } else {
                            result.push(line);
                        }
                        prevLine = line;
                    });
                    let prevIndex = null;
                    result.forEach(function (line, index) {
                        if (result[index + 1]) {
                            line.nextLineIndex = result[index + 1].index;
                        }
                    });
                    result.forEach(function (line, index) {
                        // do not collapse line if it's the only one
                        if (line.nextLineIndex == line.index + 1) {
                            line.collapsed = false;
                        }
                    });
                    return result;
                },
            },
            methods: {
                translateAll: function (limit) {
                    if (limit === undefined) {
                        limit = 50;
                    }
                    let found = false;
                    this.subLines.forEach((line) => {
                        if (found || limit <= 0) {
                            return;
                        }
                        if (!line.original.length) {
                            return;
                        }
                        if (!line.editable) {
                            return;
                        }
                        if (line.hasTranslations()) {
                            return;
                        }
                        if (!line.translationYandex.length) {
                            line.translateYandex();
                            found = true;
                        }
                        if (!line.translationGoogle.length) {
                            line.translateGoogle(() => {
                                this.translateAll(limit - 1);
                            });
                            found = true;
                        }
                    });
                },
                calculatePercentDone: function () {
                    let percent = this.translatedCount * 100 / this.subLines.length;
                    Vue.set(this, 'percentDone', percent);
                    @if ($untranslatedCount)
                    if (percent === 100) {
                        if (!$('canvas').length) {
                            window.firework.start({ autoPlay: true });
                            window.firework.fireworks();
                            setTimeout(() => {
                                $('canvas').closest('div').fadeOut(5000, function () {
                                    $('canvas').closest('div').remove();
                                });
                            }, 60000);
                        }
                    }
                    @endif
                },
                revealLines: function (line) {
                    Vue.set(this, 'viewPortActive', false);
                    for (let i = line.index - 1; i < line.nextLineIndex - 1; i++) {
                        this.subLines[i].collapsed = false;
                    }
                    setTimeout(() => {
                        Vue.set(this, 'viewPortActive', true);
                    });
                },
            },
            mounted: function () {

                this.subLines.forEach(line => {

                    if (line.translation.length) {
                        line.translationGoogle = line.translation;
                        line.approveGoogle = true;
                    }

                    addLinksToMultitran(line);
                    addExtraMethods(this, line);

                    this.calculatePercentDone();
                });

                setTimeout(() => {
                    startVideo(this.videoUrl);
                }, 2000);
            }
        });


        function startVideo(url) {
            window.mediaPlayer = dashjs.MediaPlayer().create();
            window.mediaPlayer.getDebug().setLogToBrowserConsole(false);
            window.mediaPlayer.initialize(document.querySelector("#mediaPlayer"), url, false);
        }

    </script>
    <script src="http://cdn.dashjs.org/latest/dash.all.min.js"></script>
@append
