var socket = io();
var uploader = new SocketIOFileUpload(socket);
uploader.listenOnInput($('#camera')[0]);

socket.on('state', function(state, data, error) {
    if (state === 'UPLOAD') {
        $('#camera').prop("disabled", false);
        $('#cameraButton').removeClass('disabled').text('Choose image...');
    }

    if (state === 'UPLOADING') {
        $('#camera').prop('disabled', true);
        $('#cameraButton').addClass('disabled').text('Uploading...');
    }

    if (state === 'PREPROCESSING') {
        $('#cameraButton').text('Preprocessing...');
    }

    if (state === 'PARSING') {
        $('#cameraButton').text('Parsing...');
    }

    if (state === 'MATCHING') {
        $('#cameraButton').text('Matching...');
    }

    if (state === 'GROUPING') {
        $('#cameraButton').text('Grouping...');

        for (let groupIndex of data.possibleGroups) {
            $('<div class="btn btn-lg group"></div>').text(groupIndex).attr('data-index', groupIndex).appendTo('.groupContainer');
        }
        $('<div class="btn btn-lg btn-success group"></div>').text(data.nextGroupIndex).attr('data-index', data.nextGroupIndex).appendTo('.groupContainer');
    }

    if (state === 'ERROR') {
        $('#camera').prop("disabled", false);
        $('#cameraButton').removeClass('disabled').text('Got error at ' + error.atStep + ': ' + error.message);
    }
});

$('.groupContainer').on('click', '.group', function() {
    socket.emit('group', $(this).attr('data-index'));

    $('.groupContainer').empty();
});