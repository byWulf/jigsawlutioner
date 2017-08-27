var socket = io();
var uploader = new SocketIOFileUpload(socket);

var $camera = $('#camera');
var $cameraButton = $('#cameraButton');
var $fullscreenContainer = $('.fullscreen');
var $actionContainer = $('.actionContainer');
var $canvas = $('#myCanvas');

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
        paper.setup($canvas[0]);

        var points = [];
        var degreePoints = [];
        var diffPoints = [];
        for (var i = 0; i < data.diffs.length; i++) {
            points.push({x: data.diffs[i].point[1], y: data.diffs[i].point[2]});
            degreePoints.push({x: i/3, y: data.diffs[i].deg + 700});
            diffPoints.push({x: i/3, y: data.diffs[i].diff * 2 + 700});
        }

        var path = new paper.Path({
            strokeColor: 'black',
            closed: true,
            segments: points
        });
        var path2 = new paper.Path({
            strokeColor: 'blue',
            closed: false,
            segments: degreePoints
        });
        var path2 = new paper.Path({
            strokeColor: 'red',
            closed: false,
            segments: diffPoints
        });

        new paper.Path({
            strokeColor: '#dddddd',
            closed: false,
            segments: [{x: 0, y: 810}, {x: data.diffs.length / 3, y: 810}]
        });
        for (let i = 0; i < data.diffs.length; i += 100) {
            new paper.Path({
                strokeColor: '#aaaaaa',
                closed: false,
                segments: [{x: i / 3, y: 800}, {x: i / 3, y: 820}]
            });
            if (i % 1000 == 0) {
                new paper.Path({
                    strokeColor: '#666666',
                    closed: false,
                    segments: [{x: i / 3, y: 780}, {x: i / 3, y: 840}]
                });
            }
        }

        for (let i = 0; i < data.sides.length; i++) {
            var point = data.sides[i].startPoint;
            new paper.Path({
                strokeColor: '#00ff00',
                closed: false,
                segments: [{x: point[1] - 20, y: point[2] - 20}, {x: point[1] + 20, y: point[2] + 20}]
            });
            new paper.Path({
                strokeColor: '#00ff00',
                closed: false,
                segments: [{x: point[1] - 20, y: point[2] + 20}, {x: point[1] + 20, y: point[2] - 20}]
            });

            var point = data.sides[i].endPoint;
            new paper.Path({
                strokeColor: '#00ff00',
                closed: false,
                segments: [{x: point[1] - 20, y: point[2] - 20}, {x: point[1] + 20, y: point[2] + 20}]
            });
            new paper.Path({
                strokeColor: '#00ff00',
                closed: false,
                segments: [{x: point[1] - 20, y: point[2] + 20}, {x: point[1] + 20, y: point[2] - 20}]
            });

            new paper.Path({
                strokeColor: '#00ff00',
                closed: false,
                segments: [{x: data.sides[i].fromOffset / 3, y: 500}, {x: data.sides[i].fromOffset/3, y: 800}]
            });
            new paper.Path({
                strokeColor: '#00ff00',
                closed: false,
                segments: [{x: data.sides[i].toOffset / 3, y: 500}, {x: data.sides[i].toOffset/3, y: 800}]
            });
        }

        paper.view.draw();

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