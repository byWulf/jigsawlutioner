const socket = io();
const uploader = new SocketIOFileUpload(socket);

const $camera = $('#camera');
const $cameraButton = $('#cameraButton');
const $fullscreenContainer = $('.fullscreen');
const $actionContainer = $('.actionContainer');
const $canvas = $('#myCanvas');
const $pieceList = $('#pieceList');
const $compareList = $('#compareList');
const $mainContent = $('#mainContent');
const $imageCanvas = $('.imageCanvas');
const $cornerGraphsCanvas = $('.cornerGraphsCanvas');
const $sideComparisonCanvas = $('.sideComparisonCanvas');
const $compareContainer = $('#compareContainer');

const reader = new FileReader();
reader.onload = function (e) {
  $fullscreenContainer.css('backgroundImage', 'url(' + e.target.result + ')');
};

let pendingPiece = null;
let pieces = [];

$camera.on('change', function() {
    reader.readAsDataURL(this.files[0]);
});
uploader.listenOnInput($camera[0]);

function addPieceToList(pieceIndex) {
    $pieceList.append($('<a href="#" class="list-group-item list-group-item-action"></a>').text('# ' + pieceIndex).attr('data-pieceindex', pieceIndex));
    $compareList.append($('<a href="#" class="list-group-item list-group-item-action"></a>').text('# ' + pieceIndex).attr('data-pieceindex', pieceIndex));
}

let loadingPiece = null;
let lastSelectedImage = null;
let currentPiece = null;
let loadingComparePiece = null;

function openPiece(pieceIndex) {
    $pieceList.find('.list-group-item.active').removeClass('active');
    $pieceList.find('.list-group-item[data-pieceindex="' + pieceIndex + '"]').addClass('active');

    $mainContent.find('> .loading').show();
    $mainContent.find('> .card').hide();

    loadingComparePiece = null;
    $compareList.find('.list-group-item.active').removeClass('active');
    $compareContainer.find('> canvas').hide();
    $compareContainer.find('> .loading').hide();


    loadingPiece = pieceIndex;
    socket.emit('getPiece', pieceIndex);
}

function comparePiece(pieceIndex) {
    $compareList.find('.list-group-item.active').removeClass('active');
    $compareList.find('.list-group-item[data-pieceindex="' + pieceIndex + '"]').addClass('active');

    $compareContainer.find('> .loading').show();
    $compareContainer.find('> canvas').hide();

    loadingComparePiece = pieceIndex;
    socket.emit('comparePieces', currentPiece.pieceIndex, pieceIndex);
}

socket.on('pieces', (pieceIndices) => {
    for (let i = 0; i < pieceIndices.length; i++) {
        addPieceToList(pieceIndices[i]);
    }
});

socket.on('newPiece', (pieceIndex) => {
    addPieceToList(pieceIndex);
    openPiece(pieceIndex);
});

//Open piece
socket.on('piece', (piece) => {
    if (piece.pieceIndex !== parseInt(loadingPiece, 10)) {
        return;
    }
    loadingPiece = null;
    currentPiece = piece;

    $mainContent.find('.card').find('.pieceIndex').text(piece.pieceIndex);
    $mainContent.find('.imagesNav').empty();
    for (let fileType in piece.files) {
        if (!piece.files.hasOwnProperty(fileType)) continue;

        $('<li class="nav-item"><a class="nav-link" href="#"></a></li>').find('a').text(fileType).attr('data-filetype', fileType).attr('data-filename', piece.files[fileType]).end().appendTo($mainContent.find('.imagesNav'));
    }
    if (lastSelectedImage !== null && $mainContent.find('.imagesNav a[data-filetype="' + lastSelectedImage + '"]').length) {
        $mainContent.find('.imagesNav a[data-filetype="' + lastSelectedImage + '"]').click();
    } else {
        $mainContent.find('.imagesNav a:last').click();
    }


    $mainContent.find('> .card').show();
    $mainContent.find('> .loading').hide();

    setTimeout(function() {
        //Image borders
        paper.setup($imageCanvas[0]);
        let points = [];
        for (let i = 0; i < piece.diffs.length; i++) {
            points.push({
                x: piece.diffs[i].point[1] + piece.boundingBox.left,
                y: piece.diffs[i].point[2] + piece.boundingBox.top
            });
        }

        new paper.Path({
            strokeColor: 'red',
            strokeWidth: 4,
            closed: true,
            segments: points
        });

        for (let i = 0; i < piece.sides.length; i++) {
            [piece.sides[i].startPoint, piece.sides[i].endPoint].forEach(function (point) {
                new paper.Path({
                    strokeColor: '#00ff00',
                    strokeWidth: 4,
                    closed: false,
                    segments: [{
                        x: point[1] - 20 + piece.boundingBox.left,
                        y: point[2] - 20 + piece.boundingBox.top
                    }, {x: point[1] + 20 + piece.boundingBox.left, y: point[2] + 20 + piece.boundingBox.top}]
                });
                new paper.Path({
                    strokeColor: '#00ff00',
                    strokeWidth: 4,
                    closed: false,
                    segments: [{
                        x: point[1] - 20 + piece.boundingBox.left,
                        y: point[2] + 20 + piece.boundingBox.top
                    }, {x: point[1] + 20 + piece.boundingBox.left, y: point[2] - 20 + piece.boundingBox.top}]
                });
            });

            new paper.PointText({
                point: {
                    x: (piece.sides[i].endPoint[1] - piece.sides[i].startPoint[1]) / 2 + piece.sides[i].startPoint[1] + piece.boundingBox.left - 15,
                    y: (piece.sides[i].endPoint[2] - piece.sides[i].startPoint[2]) / 2 + piece.sides[i].startPoint[2] + piece.boundingBox.top + 40
                },
                content: i,
                fillColor: 'white',
                strokeColor: 'black',
                strokeWidth: 5,
                fontWeight: 'bold',
                fontSize: 80
            });
        }

        paper.view.scale(400 / piece.dimensions.width, {x: 0, y: 0});
        paper.view.draw();

        //Corner detection
        paper.setup($cornerGraphsCanvas[0]);

        let degreePoints = [];
        let diffPoints = [];
        for (let i = 0; i < piece.diffs.length; i++) {
            degreePoints.push({x: i, y: piece.diffs[i].deg + 300});
            diffPoints.push({x: i, y: piece.diffs[i].diff * 2 + 300});
        }

        for (let i = 0; i < piece.sides.length; i++) {
            new paper.Path({
                strokeColor: '#00ff00',
                closed: false,
                segments: [{x: piece.sides[i].fromOffset, y: 0}, {x: piece.sides[i].fromOffset, y: 600}]
            });
            new paper.Path({
                strokeColor: '#00ff00',
                closed: false,
                segments: [{x: piece.sides[i].toOffset, y: 0}, {x: piece.sides[i].toOffset, y: 600}]
            });
        }

        new paper.Path({
            strokeColor: '#dddddd',
            closed: false,
            segments: [{x: 0, y: 300}, {x: piece.diffs.length, y: 300}]
        });
        for (let i = 0; i < piece.diffs.length; i += 100) {
            new paper.Path({
                strokeColor: '#bbbbbb',
                closed: false,
                segments: [{x: i, y: 150}, {x: i, y: 450}]
            });
            if (i % 1000 === 0) {
                new paper.Path({
                    strokeColor: '#999999',
                    closed: false,
                    segments: [{x: i, y: 100}, {x: i, y: 500}]
                });
            }
        }


        new paper.Path({
            strokeColor: 'blue',
            closed: false,
            segments: degreePoints
        });
        new paper.Path({
            strokeColor: 'red',
            closed: false,
            segments: diffPoints
        });

        paper.view.onResize = function() {
            paper.view.scale(1 / paper.view.scaling.x, 1, {x: 0, y: 0});
            paper.view.scale($cornerGraphsCanvas.width() / piece.diffs.length, 1, {x: 0, y: 0});
        };

        paper.view.onResize();
        paper.view.draw();
    }, 10);

    console.log(piece);
});

//Compare pieces
socket.on('comparison', (sourcePiece, comparePiece, results) => {
    if (sourcePiece.pieceIndex !== currentPiece.pieceIndex && comparePiece.pieceIndex !== parseInt(loadingComparePiece, 10)) {
        return;
    }
    loadingComparePiece = null;

    $compareContainer.find('> canvas').show();
    $compareContainer.find('> .loading').hide();

    setTimeout(function() {
        paper.setup($sideComparisonCanvas[0]);

        for (let sourceSideIndex = 0; sourceSideIndex < currentPiece.sides.length; sourceSideIndex++) {
            for (let compareSideIndex = 0; compareSideIndex < comparePiece.sides.length; compareSideIndex++) {
                let result = results[sourceSideIndex + '_' + compareSideIndex];
                let points = [];
                for (let j = 0; j < currentPiece.sides[sourceSideIndex].points.length; j++) {
                    points.push({
                        x: currentPiece.sides[sourceSideIndex].points[j].x + 500 * sourceSideIndex + 250,
                        y: currentPiece.sides[sourceSideIndex].points[j].y + 300 * compareSideIndex + 150
                    });
                }
                new paper.Path({
                    strokeColor: result.sameSide ? '#888888' : 'red',
                    closed: false,
                    segments: points
                });

                let comparePoints = [];
                for (let j = 0; j < comparePiece.sides[compareSideIndex].points.length; j++) {
                    comparePoints.push({
                        x: -comparePiece.sides[compareSideIndex].points[j].x + 500 * sourceSideIndex + 250 + result.offsetX,
                        y: -comparePiece.sides[compareSideIndex].points[j].y + 300 * compareSideIndex + 150 + result.offsetY
                    });
                }
                new paper.Path({
                    strokeColor: result.sameSide ? '#cccccc' : '#ffaa00',
                    closed: false,
                    segments: comparePoints
                });


                new paper.PointText({
                    point: {
                        x: 500 * sourceSideIndex + 250 - 250,
                        y: 300 * compareSideIndex + 150 - 80
                    },
                    content: '' + sourceSideIndex + '/' + compareSideIndex,
                    fillColor: 'black',
                    fontSize: 40
                });

                if (result.sameSide) {
                    new paper.PointText({
                        point: {
                            x: 500 * sourceSideIndex + 250 - 150,
                            y: 300 * compareSideIndex + 150
                        },
                        content: 'Same sides',
                        fillColor: '#aaaaaa',
                        fontSize: 60
                    });
                } else {
                    new paper.PointText({
                        point: {
                            x: 500 * sourceSideIndex + 250 - 250,
                            y: 300 * compareSideIndex + 150 - 40 * (currentPiece.sides[sourceSideIndex].direction === 'in' ? 1 : -1)
                        },
                        content: [
                            Math.round(result.avgDistance),
                            Math.round(Math.sqrt(result.areaDiff)),
                            Math.round(result.directLengthDiff),
                            Math.round(result.worstSingleDistance),
                            Math.round(result.nopCenterDiff),
                            Math.round(result.nopHeightDiff),
                            Math.round(result.smallNopDiff),
                            Math.round(result.bigNopDiff)
                        ].join(', '),
                        fillColor: 'black',
                        fontSize: 40
                    });

                    let sum = Math.round(
                       result.avgDistance +
                       Math.sqrt(result.areaDiff) +
                       result.directLengthDiff +
                       result.worstSingleDistance +
                       result.nopCenterDiff +
                       result.nopHeightDiff +
                       result.smallNopDiff +
                       result.bigNopDiff
                    );

                    new paper.PointText({
                        point: {
                            x: 500 * sourceSideIndex + 250 - 100,
                            y: 300 * compareSideIndex + 150 - 80 * (currentPiece.sides[sourceSideIndex].direction === 'in' ? 1 : -1)
                        },
                        content: '= ' + sum,
                        fillColor: sum <= 75 ? 'red' : 'black',
                        fontWeight: sum <= 75 ? 'bold' : 'normal',
                        fontSize: 40
                    });


                    /*
                    new paper.PointText({
                        point: {
                            x: 500 * sourceSideIndex + 250,
                            y: 300 * compareSideIndex + 150 - 40
                        },
                        content: 'AreaDiff: ' + Math.round(result.areaDiff),
                        fillColor: 'black',
                        fontSize: 40
                    });
                    new paper.PointText({
                        point: {
                            x: 500 * sourceSideIndex + 250,
                            y: 300 * compareSideIndex + 150
                        },
                        content: 'LenDiff: ' + Math.round(result.directLengthDiff),
                        fillColor: 'black',
                        fontSize: 40
                    });
                    new paper.PointText({
                        point: {
                            x: 500 * sourceSideIndex + 250,
                            y: 300 * compareSideIndex + 150 + 40
                        },
                        content: 'MaxDist: ' + Math.round(result.worstSingleDistance),
                        fillColor: 'black',
                        fontSize: 40
                    });*/
                }
            }
        }

        console.log(results);



        paper.view.onResize = function() {
            paper.view.scale(1 / paper.view.scaling.x, 1 / paper.view.scaling.y, {x: 0, y: 0});
            paper.view.scale($sideComparisonCanvas.width() / 2000, $sideComparisonCanvas.height() / 1200, {x: 0, y: 0});
        };

        paper.view.onResize();
        paper.view.draw();
    }, 10);
});

$pieceList.on('click', '.list-group-item', function() {
    openPiece($(this).attr('data-pieceindex'));

    return false;
});
$mainContent.on('click', '.imagesNav a', function() {
    lastSelectedImage = $(this).attr('data-filetype');

    $(this).closest('.nav').find('a.active').removeClass('active');
    $(this).addClass('active');

    $mainContent.find('.imagePreview').attr('src', '/images/' + $(this).attr('data-filename'));

    return false;
});
$compareList.on('click', '.list-group-item', function() {
    comparePiece($(this).attr('data-pieceindex'));

    return false;
});

socket.on('state', function(state, data, error) {
    if (state === 'UPLOADING') {
        $camera.prop('disabled', true);
        $cameraButton.addClass('disabled');
    }

    if (state === 'CHECKPARSE') {
        $camera.prop("disabled", false);
        $cameraButton.removeClass('disabled');
    }

    if (state === 'ERROR') {
        $camera.prop("disabled", false);
        $cameraButton.removeClass('disabled');

        alert('Got error at ' + error.atStep + ': ' + error.message);
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