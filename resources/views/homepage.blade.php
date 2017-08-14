@php #
/**
 * @var string $token
 * @var array $jobs
 */
@endphp

@extends('layout')

@section('contents')

    <div class="container">
        <h1>Welcome</h1>

        <div class="row">
            <div class="col-sm-6">

                <form method="post">
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label for="session_token">PHPSESSID</label>
                        <input type="text" class="form-control" value="{{ $token }}" name="session_token" id="session_token">
                    </div>
                    <button type="submit" class="btn btn-default">Submit</button>
                </form>

            </div>
        </div>

        <h1>Jobs</h1>
        <ul>
            <li v-for="job in jobs">
                <a v-bind:href="'/translate?jobId='+ job.id">@{{ job.name }}</a>
            </li>
        </ul>
    </div>

@endsection

@section('footer-scripts')
    <script type="text/javascript">

        const app = new Vue({
            el: '#app',
            data: {
                jobs: {!! j($jobs) !!},
            },
            mounted: function () {
                console.log("mounted");
            }
        });

    </script>
@append
