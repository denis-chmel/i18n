@php #
/**
 * @var \App\Models\File[] $files
 */

$filesData = [];
foreach ($files as $file) {
    $filesData[] = [
        'id' => $file->getId(),
        'name' => $file->getName(),
    ];
}

@endphp

@extends('layout')

@section('contents')

    <div id="app"
        v-cloak
        xmlns:v-bind="http://www.w3.org/1999/xhtml"
        xmlns:v-on="http://www.w3.org/1999/xhtml"
    >

        <div class="container">
            <h1>Upload ooona file</h1>

            <p>Max size {{ $maxUploadMb }} Mb</p>

            <div class="row">
                <div class="col-sm-6">

                    <form method="post" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <input type="file" name="source_file">
                        </div>
                        <button type="submit" class="btn btn-default">Submit</button>
                    </form>

                </div>
            </div>

            <br>

            <h4>Previous uploads</h4>
            <ul>
                <li v-for="file in files">
                    <a v-bind:href="'/file/'+ file.id">@{{ file.name }}</a>
                </li>
            </ul>
        </div>
    </div>

@endsection

@section('footer-scripts')
    <script type="text/javascript">

        const app = new Vue({
            el: '#app',
            data: {
                files: {!! j($filesData) !!},
            },
            mounted: function () {
                console.log("mounted");
            }
        });

    </script>
@append
