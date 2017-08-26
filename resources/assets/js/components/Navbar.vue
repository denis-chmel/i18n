<template>
    <nav class="navbar-fixed-top" v-bind:class="{ autosave: autosave }">
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
                            <div v-if="isSaving" class="saving-spinner">
                                <i class="fa-li fa fa-2x fa-cog fa-spin"></i>
                            </div>
                            <button type="button"
                                class="btn btn-default navbar-btn"
                                @click="translateAll(50)">
                                Translate 50
                            </button>
                        </li>
                        <li>
                            <button type="button"
                                class="btn btn-default navbar-btn"
                                @click="saveApproved(0)"
                                :disabled="isSaving"
                            >Save Approved</button>
                        </li>
                        <li>
                            <button type="button"
                                class="btn btn-default navbar-btn"
                                @click="saveApproved(1)"
                                :disabled="isSaving"
                            >Download</button>
                        </li>
                        <li>
                            <label>
                                <input type="checkbox"
                                    v-model="autosave"
                                    @change="toggleTimer()"
                                />
                                Autosave &amp; send heartbeat

                                <span v-if="isDebug" class="text-danger">(DEBUG MODE)</span>
                            </label>
                        </li>
                    </ul>

                    <ul class="nav navbar-nav navbar-right">
                        <li v-if="timer">
                            <i class="fa fa-play" aria-hidden="true" v-if="!timerStarted" @click="startTimer()"></i>
                            <i class="fa fa-pause" aria-hidden="true" v-if="timerStarted" @click="stopTimer()"></i>
                            <input class="timer" readonly type="text"
                                v-bind:value="timer.toString().toHHMM(true)"
                                @click="updateTimer()"
                            >
                            of
                            <input class="timer timer--end"
                                readonly
                                type="text"
                                v-bind:value="getEstimateTime().toHHMM()">
                        </li>
                        <li>{{ Math.round(percentDone * 10) / 10 }}%</li>
                        <li>{{ getEstimateTimeLeft().toHHMM() }} left</li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
</template>

<script>

    module.exports = {
        props: ['subLines', 'percentDone', 'jobId', 'isDebug'],
        data: function () {
            return {
                isSaving: false,
                autosave: false,
                timer: undefined,
                timerStarted: false,
                timerHandle: null,
            }
        },
        watch: {
            'timerStarted': function (timerStarted) {
                if (timerStarted) {
                    this.timerHandle = setInterval(() => {
                        this.timer++;
                        this.storeTimer();
                    }, 1000);
                } else {
                    clearInterval(this.timerHandle);
                }
            }
        },
        methods: {
            storeTimer: function () {
                this.$cookie.set("timer." + this.jobId, this.timer, 365);
            },
            saveApproved: function (download, isAutosave, sessionToken) {
                this.isSaving = true;
                this.$http.post('/save-approved', {
                    lines: this.subLines,
                    jobId: this.jobId,
                    download: download,
                    debug: this.isDebug,
                    isAutosave: isAutosave,
                    sessionToken: sessionToken
                }).then((response) => {
                    if (map = response.headers.map['content-filename']) {
                        let headers = {};
                        let blob = new Blob([response.data], { type: headers['content-type'] });
                        let link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = map[0];
                        link.click();
                    } else if (!isAutosave) {
                        console.error(response);
                        alert("Failed to save, ask Denis to check logs for " + (new Date()));
                    }
                }).finally((response) => {
                    this.isSaving = false;
                }).catch((response) => {
                    if (response.body.error === "Unauthorized") {
                        let sessionToken = prompt('Save has failed. Enter new PHPSESSID', '{{ $sessionToken }}');
                        if (sessionToken) {
                            this.saveApproved(download, 0, sessionToken);
                        }
                    } else {
                        alert(response.bodyText);
                    }
                });
            },
            updateTimer: function () {
                let newTime = prompt(
                    'Enter time taken in hours (e.g. 6.5)',
                    Math.round(this.timer / 60 / 6) / 10
                );
                if (newTime === null) {
                    return;
                }
                this.timer = parseFloat(newTime) * 60 * 60;
                this.storeTimer();
            },
            getEstimateTime: function () {
                return (Math.round(this.timer / this.percentDone / 10) * 1000).toString();
            },
            getEstimateTimeLeft: function () {
                return (Math.round(this.timer * (1 / this.percentDone * 100 - 1))).toString();
            },
            secondsToTime: function (seconds) {
                return seconds;
            },
            startTimer: function () {
                if (!this.autosave) {
                    this.timerStarted = true;
                }
            },
            stopTimer: function () {
                if (!this.autosave) {
                    this.timerStarted = false;
                }
            },
            toggleTimer: function () {
                this.timerStarted = this.autosave;
            },
            autosaveIfNeeded: function () {
                if (this.autosave) {
                    this.saveApproved(0, 1);
                }
            },
            updateWorklog: function () {
                if (this.autosave) {
                    this.$http.post('/updateWorklog', {
                        jobId: this.jobId,
                        debug: this.isDebug,
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
                            jobId: this.jobId,
                            debug: this.isDebug,
                        }).then((response) => {
                            // nothing
                        }).catch((response) => {
                            alert('setUserWorkingActivityStatus error:' + response.bodyText);
                        });
                    }
                }
            },
        },
        mounted: function () {
            let second = this.isDebug ? 100 : 1000;

            this.timer = this.$cookie.get("timer." + this.jobId) || 0;
            setInterval(this.autosaveIfNeeded, 3 * 60 * second); // each 3 min
            setInterval(this.updateWorklog, 60 * second); // each 1 min
            setInterval(this.setUserWorkingActivityStatus, 4 * 60 * second); // each 4 min
        },
    };

</script>

<style lang="scss" scoped>


</style>
