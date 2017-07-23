var socket = io();
var uploader = new SocketIOFileUpload(socket);

var $camera = $('#camera');
var $cameraButton = $('#cameraButton');
var $fullscreenContainer = $('.fullscreen');

var reader = new FileReader();
reader.onload = function (e) {
  $fullscreenContainer.css('backgroundImage', 'url(' + e.target.result + ')').css('backgroundSize', 'cover');
};

$camera.on('change', function() {
    reader.readAsDataURL(this.files[0]);
});
uploader.listenOnInput($camera[0]);

socket.on('state', function(state, data, error) {
    if (state === 'UPLOAD') {
        $camera.prop("disabled", false);
        $cameraButton.removeClass('disabled').text('Choose image...');
        $fullscreenContainer.css('backgroundImage', '');
    }

    if (state === 'UPLOADING') {
        $camera.prop('disabled', true);
        $cameraButton.addClass('disabled').text('Uploading...');
    }

    if (state === 'PREPROCESSING') {
        $cameraButton.text('Preprocessing...');
    }

    if (state === 'PARSING') {
        $cameraButton.text('Parsing...');
        $fullscreenContainer.css('backgroundImage', 'url(/images/' + data + ')').css('backgroundSize', 'contain');
    }

    if (state === 'MATCHING') {
        $cameraButton.text('Matching...');
        $fullscreenContainer.css('backgroundImage', 'url(/images/' + data + ')').css('backgroundSize', 'contain');
    }

    if (state === 'GROUPING') {
        $cameraButton.text('Grouping...');

        for (let groupIndex of data.possibleGroups) {
            $('<div class="btn btn-lg group"></div>').text(groupIndex).attr('data-index', groupIndex).appendTo('.groupContainer');
        }
        $('<div class="btn btn-lg btn-success group"></div>').text(data.nextGroupIndex).attr('data-index', data.nextGroupIndex).appendTo('.groupContainer');
        $('<div class="btn btn-lg btn-danger group"></div>').text('Falsch').attr('data-index', 'wrong').appendTo('.groupContainer');
    }

    if (state === 'ERROR') {
        $camera.prop("disabled", false);
        $cameraButton.removeClass('disabled').text('Got error at ' + error.atStep + ': ' + error.message);
        $fullscreenContainer.css('backgroundImage', '');
    }
});

$('.groupContainer').on('click', '.group', function() {
    socket.emit('group', $(this).attr('data-index'));

    $('.groupContainer').empty();
});