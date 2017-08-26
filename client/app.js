var socket = io();
var uploader = new SocketIOFileUpload(socket);

var $camera = $('#camera');
var $cameraButton = $('#cameraButton');
var $fullscreenContainer = $('.fullscreen');
var $actionContainer = $('.actionContainer');

var reader = new FileReader();
reader.onload = function (e) {
  $fullscreenContainer.css('backgroundImage', 'url(' + e.target.result + ')');
};

var pendingPiece = null;
var pieces = [];

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
        $fullscreenContainer.css('backgroundImage', 'url(/images/' + data + ')');
    }

    if (state === 'CHECKPARSE') {
        pendingPiece = data;
        pendingPiece.maskImage = $('<img>');
        pendingPiece.maskImage.attr('src', '/images/' + data.maskFilename);
        pendingPiece.maskImage.hide().appendTo('.pieceContainer');
        pendingPiece.maskImage

        $fullscreenContainer.css('backgroundImage', 'url(/images/' + data.filename + ')');

        $actionContainer.empty();
        $('<h2>Wurden die Ecken des Teils korrekt erkannt?</h2>').appendTo($actionContainer);
        $('<div class="btn-group">' +
            '<div class="btn btn-lg btn-success action" data-action="parseCorrect">Korrekt</div>' +
            '<div class="btn btn-lg btn-danger action" data-action="parseWrong">Falsch</div>' +
        '</div>').appendTo($actionContainer);
        $actionContainer.show();
    }

    if (state === 'MATCHING') {
        $cameraButton.text('Matching...');
        pieces.push(pendingPiece);


    }

    if (state === 'GROUPING') {
        $cameraButton.text('Grouping...');

        $('.groupContainer').empty();
        for (let groupIndex of data.possibleGroups) {
            $('<div class="btn btn-lg group"></div>').text(groupIndex).attr('data-index', groupIndex).appendTo('.groupContainer');
        }
        $('<div class="btn btn-lg btn-success group"></div>').text(data.nextGroupIndex).attr('data-index', data.nextGroupIndex).appendTo('.groupContainer');
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
$actionContainer.on('click', '.action', function() {
    socket.emit($(this).attr('data-action'));

    $actionContainer.hide();
});