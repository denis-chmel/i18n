@php #
/**
 * @var array $lines
 * @var array $bannedWords
 * @var int $jobId
 */

if ($no = request('box')) {
    $lines = array_slice($lines, $no - 1, 1);
}
//$lines = array_slice($lines, 0, 10);

@endphp

@extends('layout')

@section('contents')

    <nav id='nav_bar' v-bind:class="{ autosave: autosave }">
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
                            <button type="button" class="btn btn-primary navbar-btn" @click="translateAll()">Translate
                                Unapproved
                            </button>
                        </li>
                        <li class="divider">&nbsp;&nbsp;</li>
                        <li>
                            <button type="button" class="btn btn-default navbar-btn" @click="saveApproved(0)"
                                :disabled="isSaving"
                            >Save Approved
                            </button>
                        </li>
                        <li class="divider">&nbsp;&nbsp;</li>
                        <li>
                            <button type="button" class="btn btn-default navbar-btn" @click="saveApproved(1)"
                                :disabled="isSaving"
                            >Download All
                            </button>
                        </li>
                        <li>
                            <label>
                                <input type="checkbox" v-model="autosave" />
                                Autosave &amp; send heartbeat
                            </label>
                        </li>
                    </ul>

                    <ul class="nav navbar-nav navbar-right">
                        <li><a style="text-decoration: none">Approved @{{ percentDone }}%</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <video data-dashjs-player id="videoPlayer" controls></video>

    <div class="container">
        <table class="translations">

            <tbody>
            <tr valign="top" v-for="line in subLines" v-bind:class="{ italic: line.isItalic }">
                <td>
                    <a @click="playPhrase(line)">@{{ line.index }}</a>
                </td>
                <td>
                    <pre class="original" v-html="line.html"></pre>
                </td>
                <td v-if="line.editable">
                    <button tabindex="-1" class="btn btn-default btn-xs" type="button" @click="translateGoogle(line)">
                        G
                    </button>
                    <textarea
                        :disabled="line.disabled == true"
                        @click="approveGoogle(line)"
                        @keyup="approveGoogle(line)"
                        v-bind:class="{ loading: line.loadingGoogle, approved: line.approveGoogle }"
                        v-model="line.translationGoogle"
                    ></textarea>
                </td>
                <td v-if="line.editable">
                    <button tabindex="-1" class="btn btn-default btn-xs" type="button" @click="translateYandex(line)">
                        Я
                    </button>
                    <textarea
                        :disabled="line.disabled == true"
                        @click="approveYandex(line)"
                        @keyup="approveYandex(line)"
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
        }

        const app = new Vue({
            el: '#app',
            data: {
                isSaving: false,
                percentDone: 0,
                autosave: false,
                videoUrl: {!! j($videoUrl) !!},
                subLines: {!! j($lines) !!},
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
                    window.player.pause();
                    let hasTranslation = line.translationYandex.length > 0;
                    Vue.set(line, 'approveYandex', hasTranslation);
                    if (hasTranslation) {
                        Vue.set(line, 'approveGoogle', false);
                    }
                    this.calculatePercentDone();
                },
                approveGoogle: function (line) {
                    window.player.pause();
                    let hasTranslation = line.translationGoogle.length > 0;
                    Vue.set(line, 'approveGoogle', hasTranslation);
                    if (hasTranslation) {
                        Vue.set(line, 'approveYandex', false);
                    }
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
                calculatePercentDone: function () {
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
                saveApproved: function (download, isAutosave) {
                    this.isSaving = true;
                    this.$http.post('/save-approved', {
                        lines: this.subLines,
                        jobId: {{ $jobId }},
                        download: download,
                        isAutosave: isAutosave,
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
                autosaveIfNeeded: function () {
                    if (this.autosave) {
                        this.saveApproved(0, 1);
                    }
                },
                updateWorklog: function () {
                    if (this.autosave) {
                        this.$http.post('/updateWorklog', {
                            jobId: {{ $jobId }},
                        }).then((response) => {
                            // nothing
                        }).catch((response) => {
                            alert('updateWorklog error:' + response.bodyText);
                        });
                    }
                },
                setUserWorkingActivityStatus: function () {
                    if (this.autosave) {
                        if (this.autosave) {
                            this.$http.post('/setUserWorkingActivityStatus', {
                                jobId: {{ $jobId }},
                            }).then((response) => {
                                // nothing
                            }).catch((response) => {
                                alert('setUserWorkingActivityStatus error:' + response.bodyText);
                            });
                        }
                    }
                },
                playPhrase: function(line){
                    window.player.play();
                    window.player.seek(line.secondStart);
//                    document.getElementById('videoPlayer').seek(100);
                }
            },
            mounted: function () {
                this.subLines.forEach(line => {

                    if (line.translation.length) {
                        line.translationGoogle = line.translation;
                        line.approveGoogle = true;
                    }

                    addLinksToMultitran(line);

                    this.calculatePercentDone();
                });

                let second = 100;
                setInterval(this.autosaveIfNeeded, 3 * 60 * second); // each 3 min
                setInterval(this.updateWorklog, 60 * second); // each 1 min
                setInterval(this.setUserWorkingActivityStatus, 4 * 60 * second); // each 4 min

                setTimeout(a => {
                    startVideo(this.videoUrl);
                }, 1000);
            }
        });


        function startVideo(url) {
            window.player = dashjs.MediaPlayer().create();
            window.player.initialize(document.querySelector("#videoPlayer"), url, false);
        }

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
    <script src="http://cdn.dashjs.org/latest/dash.all.min.js"></script>
    <style>
        video {
            width: 400px;
            position: fixed;
            z-index: 1;
            top: 0;
            right: 0;
            transform: scale(0.5) translate(100px, 100px);
            transition: all 500ms;
        }
        video:hover {
            transform: scale(1) translate(0, 100px);
        }
    </style>
@append
