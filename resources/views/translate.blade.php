@php #
/**
 * @var array $lines
 * @var array $bannedWords
 * @var int $jobId
 */

if ($no = request('box')) {
    $lines = array_slice($lines, $no - 1, 1);
}
$lines = array_slice($lines, 0, 10);

@endphp

@extends('layout')

@section('contents')

    <nav id='nav_bar'>
        <div class="navbar navbar-default navbar-static">
            <div class="container">
                <!-- .btn-navbar is used as the toggle for collapsed navbar content -->
                <a class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="glyphicon glyphicon-bar"></span>
                    <span class="glyphicon glyphicon-bar"></span>
                    <span class="glyphicon glyphicon-bar"></span>
                </a>
                <div class="navbar-collapse collapse">
                    <ul class="nav navbar-nav">
                        <li>
                            <button type="button" class="btn btn-primary navbar-btn" @click="translateAll()">Translate Unapproved</button>
                        </li>
                        <li class="divider">&nbsp;&nbsp;</li>
                        <li>
                            <button type="button" class="btn btn-default navbar-btn" @click="saveApproved()"
                                :disabled="isSaving"
                            >Save Approved</button>
                        </li>
                        <li class="divider">&nbsp;&nbsp;</li>
                        <li>
                            <button type="button" class="btn btn-default navbar-btn" @click="saveApproved(1)"
                                :disabled="isSaving"
                            >Download All</button>
                        </li>
                    </ul>

                    <ul class="nav navbar-nav navbar-right">
                        <li><a style="text-decoration: none">Approved @{{ percentDone }}%</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
    <table class="translations">

        <tbody>
        <tr valign="top" v-for="line in subLines" v-bind:class="{ italic: line.isItalic }">
            <td>
                 @{{ line.index }}
            </td>
            <td>
                <pre class="original" v-html="line.html"></pre>
            </td>
            <td v-if="line.editable">
                <button tabindex="-1" class="btn btn-default btn-xs" type="button" @click="translateGoogle(line)">G</button>
                <textarea
                    :disabled="line.disabled == true"
                    @click="approveGoogle(line)"
                    v-bind:class="{ loading: line.loadingGoogle, approved: line.approveGoogle }"
                    v-model="line.translationGoogle"
                ></textarea>
            </td>
            <td v-if="line.editable">
                <button tabindex="-1" class="btn btn-default btn-xs" type="button" @click="translateYandex(line)">Я</button>
                <textarea
                    :disabled="line.disabled == true"
                    @click="approveYandex(line)"
                    v-bind:class="{ loading: line.loadingYandex, approved: line.approveYandex }"
                    v-model="line.translationYandex"
                ></textarea>
            </td>
        </tr>
        </tbody>

    </table>
    </div>

@endsection

@section('footer-scripts')
    <script type="text/javascript">

        let bannedWords = {!! json_encode($bannedWords) !!};

        String.prototype.startsWithAny = function (searchStrings, position) {
            let string = this.toLowerCase();
            let found = false;
            searchStrings.forEach(searchString => {
                if (string.substr(position || 0, searchString.length) === searchString) {
                    found = true;
                }

            });
            return found;
        };

        function trim(s, mask) {
            while (~mask.indexOf(s[0])) {
                s = s.slice(1);
            }
            while (~mask.indexOf(s[s.length - 1])) {
                s = s.slice(0, -1);
            }
            return s;
        }

        function addLinksToMultitran(line) {

            let words = line.original.match(/\S+/g);

            words.forEach((word, index) => {
                words[index] = trim(word, '.,-!?:');
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

            unique.sort();

            unique.forEach((word) => {
                let singular = window.pluralize.singular(word);
                if (bannedWords.includes(singular.toLowerCase())) {
                    return;
                }
                let regex = new RegExp('\\b' + word + '\\b');
                html = html.replace(regex, `<a target="_blank" tabindex="-1" href="https://www.multitran.ru/c/m.exe?s=${encodeURIComponent(singular)}">${word}</a>`);
            });
            line.html = html;

            return line;
        }

        const app = new Vue({
            el: '#app',
            data: {
                isSaving: false,
                percentDone: 0,
                subLines: {!! j($lines) !!}
            },
            methods: {
                translateYandex: function (line, delay) {
                    Vue.set(line, 'loadingYandex', true);
                    Vue.set(line, 'translationYandex', line.original);
                    Vue.set(this.subLines, 'reload', Math.random());
                    setTimeout(a => {
                        window.translateYandex.translate(line.original, { to: 'ru' }, function (err, res) {
                            line.translationYandex = res.text[0];
                            line.loadingYandex = false;
                        });
                    }, delay || 200);
                },
                approveYandex: function (line) {
                    Vue.set(line, 'approveYandex', true);
                    Vue.set(line, 'approveGoogle', false);
                    this.calculatePercentDone();
                },
                approveGoogle: function (line) {
                    Vue.set(line, 'approveYandex', false);
                    Vue.set(line, 'approveGoogle', true);
                    this.calculatePercentDone();
                },
                translateGoogle: function (line, delay) {
                    Vue.set(line, 'loadingGoogle', true);
                    Vue.set(line, 'translationGoogle', line.original);
                    setTimeout(a => {

                        window.googleTranslate('en', 'ru', line.original, response => {
                            response = JSON.parse(response);
                            line.translationGoogle = response.translation;
                            line.loadingGoogle = false;
                        });

                    }, delay || 0);
                },
                translateAll: function () {
                    this.subLines.forEach((line, index) => {
                        if (!line.editable) {
                            return;
                        }
                        if (!line.approveYandex || !line.translationYandex.length) {
                            this.translateYandex(line, index * 200);
                            line.approveYandex = false;
                        }
                        if (!line.approveGoogle || !line.translationGoogle.length) {
                            this.translateGoogle(line, index * 200);
                            line.approveGoogle = false;
                        }
                    });
                },
                translateAllYandex: function () {
                    this.subLines.forEach((line, index) => {
                        this.translateYandex(line, index * 200);
                    });
                },
                translateAllGoogle: function () {
                    this.subLines.forEach((line, index) => {
                        this.translateGoogle(line, index * 200);
                    });
                },
                calculatePercentDone: function() {
                    let total = this.subLines.length;
                    let translated = this.subLines.filter(line => {
                        return line.approveYandex || line.approveGoogle ? line : false;
                    });
                    let percent = Math.ceil(translated.length * 100 / this.subLines.length);
                    Vue.set(this, 'percentDone', percent);
                },
                exportAll: function () {
                    this.$http.post('/export-all', {
                        lines: this.subLines,
                        jobId: {{ $jobId }},
                    }).then((response) => {
                        let headers = {};
                        let blob = new Blob([response.data], { type: headers['content-type'] });
                        let link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = response.headers.map['content-filename'][0];
                        link.click();
                    });
                },
                saveApproved: function (download) {
                    this.isSaving = true;
                    this.$http.post('/save-approved', {
                        lines: this.subLines,
                        jobId: {{ $jobId }},
                        download: download,
                    }).then((response) => {
                        if (download) {
                            let headers = {};
                            let blob = new Blob([response.data], { type: headers['content-type'] });
                            let link = document.createElement('a');
                            link.href = window.URL.createObjectURL(blob);
                            link.download = response.headers.map['content-filename'][0];
                            link.click();
                        }
                    }).finally((response) => {
                        this.isSaving = false;
                    }).catch((response) => {
                        alert(response.bodyText);
                    });
                },
            },
            mounted: function () {
                this.subLines.forEach(line => {

                    if (line.translation.length) {
                        line.translationGoogle = line.translation;
                        line.approveGoogle = true;
                    }

                    line = addLinksToMultitran(line);

                    this.calculatePercentDone();

                });
            }
        });


        $(document).ready(function () {
            $(window).scroll(function () {

                if ($(window).scrollTop() > 250) {
                    $('#nav_bar').addClass('navbar-fixed-top');
                }

                if ($(window).scrollTop() < 251) {
                    $('#nav_bar').removeClass('navbar-fixed-top');
                }
            });
        });

    </script>

@append
