@php #
/**
 * @var array $lines
 * @var array $bannedWords
 * @var boolean $isDebug
 * @var boolean $isQaMode
 * @var int $untranslatedCount
 */

if ($no = request('box')) {
    $lines = array_slice($lines, $no - 1, 1);
}
// $lines = array_slice($lines, 20, 40);

$hasUntranslated = false;
foreach ($lines as $line) {
    if (!$line['translation']) {
        $hasUntranslated = true;
        break;
    }
}

$appData = [
    'viewPortActive' => true,
    'percentDone' => 0,
    'jobId' => 0,
    'isDebug' => (int)$isDebug,
    'subLines' => $lines,
    'bannedWords' => $bannedWords,
    'isQaMode' => $isQaMode,
];

@endphp

@extends('layout')

@section('contents')

    <!--suppress HtmlUnknownTag, HtmlUnknownAttribute -->
    <div id="app"
        v-cloak
        xmlns:v-bind="http://www.w3.org/1999/xhtml"
        xmlns:v-on="http://www.w3.org/1999/xhtml"
    >

        <navbar
            v-bind:sub-lines="subLines"
            v-bind:percent-done="percentDone"
            v-bind:is-qa-mode="isQaMode"
            v-bind:percent-done="percentDone"
            v-bind:unprocessed-qa-count="unprocessedQaCount"
            v-bind:translated-count="translatedCount"
            v-bind:job-id="jobId"
            v-bind:is-debug="{{ (int)$isDebug }}"
        ></navbar>

        <div class="container">
            <table class="translations">
                <tbody>
                <tr is="phrase"
                    v-for="line in nonCollapsedLines" :key="line.index"
                    :in-viewport-offset-top='1000'
                    :in-viewport-active='viewPortActive'
                    v-bind:line="line"
                    v-bind:is-qa-mode="isQaMode"
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

    <script id="appData" type="application/json">
        {!! json_encode($appData) !!}
    </script>

    <script type="text/javascript">

        let appData = JSON.parse($('#appData').html());

        function addExtraMethods(vue, line) {
            line.hasTranslations = function () {
                return (line.translationAlt.length + line.translationGoogle.length > 0);
            };

            line.refresh = function() {
                Vue.set(vue.subLines, 'reload', Math.random());
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
                Vue.set(line, 'translationAlt', line.original);
                line.refresh();
                window.translateYandex.translate(line.original, { to: 'ru' }, function (err, res) {
                    line.translationAlt = res.text[0];
                    line.loadingYandex = false;
                    if (callback) callback();
                });
            };

            line.translateReverso = function (callback) {
                let line = this;
                Vue.set(line, 'loadingReverso', true);
                line.refresh();
                let original = encodeURI(line.original);
                vue.$http.get('/translate-reverso?from=en&to=ru&text=' + original).then((response) => {
                    Vue.set(line, 'loadingReverso', false);
                    line.reversoInfo = response.body;
                    if (!line.reversoInfo.length) {
                        Vue.set(line, 'disableReversoInfo', true);
                    }
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
                if (appData.bannedWords.includes(singular.toLowerCase())) {
                    return;
                }
                let regex = new RegExp('\\b' + word + '\\b');
                html = html.replace(regex, `<a target="${target}" tabindex="-1" href="https://www.multitran.ru/c/m.exe?s=${encodeURIComponent(singular)}">${word}</a>`);
            });
            line.html = html.replace(/\n/, '<span class="enter"></span>\n');
        }

        const app = new Vue({
            el: '#app',
            data: appData,
            computed: {
                translatedCount: function () {
                    let translated = this.subLines.filter(line => {
                        return line.approveYandex || line.approveGoogle ? line : false;
                    });
                    return translated.length;
                },
                unprocessedQaCount: function () {
                    let unprocessed = this.subLines.filter(line => {
                        return line.qaUnprocessed ? line : false;
                    });
                    return unprocessed.length;
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
                    let lastLine = this.subLines[this.subLines.length - 1];
                    result.forEach((line, index) => {
                        line.nextLineIndex = lastLine.index + 1;
                        if (result[index + 1]) {
                            line.nextLineIndex = result[index + 1].index;
                        }
                    });
                    return result;
                },
            },
            methods: {
                calculatePercentDone: function () {
                    let percent = this.translatedCount * 100 / this.subLines.length;
                    Vue.set(this, 'percentDone', percent);
                    @if ($hasUntranslated)
                    if (percent === '100%') {
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
                    for (let i = line.index - 1; i < line.nextLineIndex; i++) {
                        if (this.subLines[i]) {
                            this.subLines[i].collapsed = false;
                        }
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
            }
        });

    </script>
@append
