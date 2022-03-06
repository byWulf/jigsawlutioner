<?php

declare(strict_types=1);

namespace Bywulf\Jigsawlutioner\Service;

use Bywulf\Jigsawlutioner\Dto\Solution;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class SolutionOutputter
{
    private PointService $pointService;
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->pointService = new PointService();
        $this->filesystem = new Filesystem();
    }

    public function outputAsHtml(Solution $solution, string $placementHtmlPath, string $transparentImagePathPattern, float $resizeFactor = 0.1, string $previousFile = null, string $nextFile = null, array $ignoredSideKeys = []): void
    {
        $width = 0;
        foreach ($solution->getGroups() as $group) {
            $width = max($width, $group->getWidth());
        }

        $groups = [];

        foreach ($solution->getGroups() as $group) {
            $widths = [];
            $heights = [];
            $lefts = [];
            $tops = [];
            foreach ($group->getPlacements() as $placement) {
                $widths[$placement->getX()][] = $placement->getWidth();
                $heights[$placement->getY()][] = $placement->getHeight();
            }
            foreach ($widths as $x => $widthArray) {
                $widths[$x] = array_sum($widthArray) / count($widthArray);
            }
            foreach ($heights as $y => $heightsArray) {
                $heights[$y] = array_sum($heightsArray) / count($heightsArray);
            }

            $currentLeft = 0;
            $lastWidth = 0;
            for ($x = min(array_keys($widths)); $x <= max(array_keys($widths)); $x++) {
                $currentLeft += ($widths[$x] ?? $lastWidth) / 2;
                $lefts[$x] = $currentLeft;
                $currentLeft += ($widths[$x] ?? $lastWidth) / 2;

                if (isset($widths[$x])) {
                    $lastWidth = $widths[$x];
                }
            }

            $currentTop = 0;
            $lastHeight = 0;
            for ($y = min(array_keys($heights)); $y <= max(array_keys($heights)); $y++) {
                $currentTop += ($heights[$y] ?? $lastHeight) / 2;
                $tops[$y] = $currentTop;
                $currentTop += ($heights[$y] ?? $lastHeight) / 2;

                if (isset($heights[$x])) {
                    $lastHeight = $heights[$x];
                }
            }

            $pieces = [];
            $pieceIndexes = [];
            foreach ($group->getPlacements() as $placement) {
                $piece = $placement->getPiece();

                $topSide = $piece->getSide($placement->getTopSideIndex());
                $bottomSide = $piece->getSide($placement->getTopSideIndex() + 2);

                $rotation = -$this->pointService->getAverageRotation($topSide->getEndPoint(), $bottomSide->getStartPoint(), $bottomSide->getEndPoint(), $topSide->getStartPoint());

                $left = $lefts[$placement->getX()];
                $top = $tops[$placement->getY()];
                $center = $this->pointService->getAveragePoint([
                    $piece->getSide(0)->getStartPoint(),
                    $piece->getSide(1)->getStartPoint(),
                    $piece->getSide(2)->getStartPoint(),
                    $piece->getSide(3)->getStartPoint(),
                ]);

                $readableContext = [];
                $matchingKey = [];

                if ($placement->getContext() !== null) {
                    foreach ($placement->getContext() as $sideIndex => $sideContext) {
                        $readableContext[$sideIndex] = '#' . ($sideContext['matchedProbabilityIndex'] !== null ? $sideContext['matchedProbabilityIndex'] + 1 : '---') .
                            ' (' .
                            implode(
                                ', ',
                                array_map(
                                    fn (string $key, float $num): string => $key . ': ' . round($num, 2),
                                    array_slice(array_keys($sideContext['probabilities']), 0, 20),
                                    array_slice($sideContext['probabilities'], 0, 20)
                                )
                            ) .
                            ($sideContext['matchedProbabilityIndex'] > 19 ? ', ..., ' . round(array_values($sideContext['probabilities'])[$sideContext['matchedProbabilityIndex']], 2) : '') .
                            ')';
                        $matchingKey[$sideIndex] = $sideContext['matchingKey'];
                    }
                }

                $pieces[] = [
                    'src' => Path::makeRelative(
                        sprintf($transparentImagePathPattern, $piece->getIndex()),
                        dirname($placementHtmlPath)
                    ),
                    'style' => [
                        'left' => ($left * $resizeFactor) . 'px',
                        'top' => ($top * $resizeFactor) . 'px',
                        'width' => ($piece->getImageWidth() * $resizeFactor) . 'px',
                        'height' => ($piece->getImageHeight() * $resizeFactor) . 'px',
                        'margin-left' => -($piece->getImageWidth() / 2 * $resizeFactor) . 'px',
                        'margin-top' => -($piece->getImageHeight() / 2 * $resizeFactor) . 'px',
                        'transform' => 'rotate(' . $rotation . 'deg) translateX(' . ((($piece->getImageWidth() / 2) - $center->getX()) * $resizeFactor) . 'px) translateY(' . ((($piece->getImageHeight() / 2) - $center->getY()) * $resizeFactor) . 'px)',
                    ],
                    'overlayStyle' => [
                        'left' => (($left - $placement->getWidth() / 2) * $resizeFactor) . 'px',
                        'top' => (($top - $placement->getHeight() / 2) * $resizeFactor) . 'px',
                        'width' => ($placement->getWidth() * $resizeFactor) . 'px',
                        'height' => ($placement->getHeight() * $resizeFactor) . 'px',
                    ],
                    'title' => sprintf(
                        'Piece #%s, TopSide: %s, X: %s, Y: %s',
                        $placement->getPiece()->getIndex(),
                        $placement->getTopSideIndex(),
                        $placement->getX(),
                        $placement->getY()
                    ),
                    'readableContext' => $readableContext,
                    'matchingKey' => $matchingKey,
                    'number' => $placement->getPiece()->getIndex() . '/' . $placement->getTopSideIndex(),
                ];
                $pieceIndexes[] = $placement->getPiece()->getIndex();
            }
            $groups[] = [
                'containerStyle' => [
                    'width' => (array_sum($widths) * $resizeFactor) . 'px',
                    'height' => (array_sum($heights) * $resizeFactor) . 'px',
                ],
                'solutionStyle' => [
                ],
                'pieces' => $pieces,
                'pieceIndexes' => $pieceIndexes,
            ];
        }

        $this->writeHtml(
            $groups,
            $placementHtmlPath,
            $resizeFactor,
            $previousFile ? Path::makeRelative($previousFile, dirname($placementHtmlPath)) : null,
            $nextFile ? Path::makeRelative($nextFile, dirname($placementHtmlPath)) : null,
            $ignoredSideKeys,
        );
    }

    private function writeHtml(array $groups, string $placementHtmlPath, float $resizeFactor, string $previousFile = null, string $nextFile = null, array $ignoredSideKeys = []): void
    {
        $twig = new Environment(new ArrayLoader([
            'solution' => <<<HTML
                <html>
                    <head>
                        <style>
                            .solution-container {
                                background-color: #cde;
                                box-shadow: 0 0 {{ 100 * resizeFactor }}px #cde, 0 0 {{ 100 * resizeFactor }}px #cde, 0 0 {{ 100 * resizeFactor }}px #cde;
                                margin: {{ 300 * resizeFactor }}px;
                                float: left;
                            }
                            
                            .solution {
                                position: relative;
                            }
                            
                            .solution .piece {
                                position: absolute;
                                z-index: 1;
                            }
                            
                            .solution .piece-overlay {
                                position: absolute;
                                z-index: 5;
                            }
                            .solution .piece-overlay:hover {
                                background-color: rgba(255, 255, 255, 0.2);
                            }
                            .solution .piece-overlay-left {
                                position: absolute;
                                width: 30%;
                                height: 80%;
                                left: 0;
                                top: 10%;
                            }
                            .solution .piece-overlay-right {
                                position: absolute;
                                width: 30%;
                                height: 80%;
                                right: 0;
                                top: 10%;
                            }
                            .solution .piece-overlay-top {
                                position: absolute;
                                width: 80%;
                                height: 30%;
                                left: 10%;
                                top: 0;
                            }
                            .solution .piece-overlay-bottom {
                                position: absolute;
                                width: 80%;
                                height: 30%;
                                left: 10%;
                                bottom: 0;
                            }
                            .solution .piece-overlay-side:hover {
                                background-color: rgba(255, 200, 150, 0.3);
                            }
                            
                            .solution .piece-overlay .number {
                                position: absolute;
                                left: 50%;
                                width: 100px;
                                margin-left: -50px;
                                color: #000;
                                text-shadow: 0 0 1px white, 0 0 1px white, 0 0 1px white, 0 0 1px white, 0 0 1px white, 0 0 2px white, 0 0 2px white, 0 0 2px white;
                                font-size: 70%;
                                text-align: center;
                                line-height: 29px;
                                transform: rotate(-45deg);
                                pointer-events: none;
                            }
                        </style>
                        <script>
                            const ignoredMatches = {{ ignoredMatches|json_encode|raw }};
                            
                            function ignoreSide(sideKey) {
                                ignoredMatches.push(sideKey);
                                
                                document.getElementById('ignored-matches').value = JSON.stringify(ignoredMatches);
                            }
                        </script>
                    </head>
                    <body>
                        <div class="file-navigator">
                            {% if previousFile %}<a href="{{ previousFile }}">Previous step</a>{% endif %}
                            {% if nextFile %}<a href="{{ nextFile }}">Next step</a>{% endif %}
                            <input type="text" readonly id="ignored-matches" value="{{ ignoredMatches|json_encode }}">
                        </div>
                        {% for group in groups %}
                            <div class="solution-container" style="{% for name, value in group.containerStyle %}{{ name }}: {{ value }};{% endfor %}">
                                <div class="solution" style="{% for name, value in group.solutionStyle %}{{ name }}: {{ value }};{% endfor %}" data-piece-indexes="{{ group.pieceIndexes|join(',') }}">
                                    {% for piece in group.pieces %}
                                        <img class="piece" src="{{ piece.src }}" style="{% for name, value in piece.style %}{{ name }}: {{ value }};{% endfor %}">
                                        <div class="piece-overlay" style="{% for name, value in piece.overlayStyle %}{{ name }}: {{ value }};{% endfor %}" title="{{ piece.title }}">
                                            <div class="piece-overlay-side piece-overlay-top" title="{{ piece.readableContext[0] }}" onclick="ignoreSide('{{ piece.matchingKey[0] }}')"></div>
                                            <div class="piece-overlay-side piece-overlay-left" title="{{ piece.readableContext[1] }}" onclick="ignoreSide('{{ piece.matchingKey[1] }}')"></div>
                                            <div class="piece-overlay-side piece-overlay-bottom" title="{{ piece.readableContext[2] }}" onclick="ignoreSide('{{ piece.matchingKey[2] }}')"></div>
                                            <div class="piece-overlay-side piece-overlay-right" title="{{ piece.readableContext[3] }}" onclick="ignoreSide('{{ piece.matchingKey[3] }}')"></div>
                                            <div class="number">{{ piece.number }}</div>
                                        </div>
                                    {% endfor %}
                                </div>
                            </div>
                        {% endfor %}
                    </body>
                </html>
            HTML
        ]));

        $this->filesystem->dumpFile($placementHtmlPath, $twig->render('solution', ['groups' => $groups, 'resizeFactor' => $resizeFactor, 'previousFile' => $previousFile, 'nextFile' => $nextFile, 'ignoredMatches' => $ignoredSideKeys]));
    }

    public function outputAsText(Solution $solution): void
    {
        foreach ($solution->getGroups() as $index => $group) {
            echo 'Group #' . $index . ':' . PHP_EOL;
            foreach ($group->getPlacements() as $placement) {
                echo "\t" . 'x: ' . $placement->getX() . ', y: ' . $placement->getY() . ', top side: ' . $placement->getTopSideIndex() . ', pieceIndex: ' . $placement->getPiece()->getIndex() . PHP_EOL;
            }
            echo PHP_EOL;
        }
    }
}
