<?php

namespace App;

class RomsFilter
{
    /** @var string */
    protected $dirPath;

    /** @var array */
    protected $regionsOrder;

    /** @var array */
    protected $toRemovePatterns;

    /** @var bool */
    protected $onlyInfo;

    /** @var array */
    protected $toRemoveKeys = [];

    /** @var array */
    protected $unknownKeys = [];

    /**
     * @param string $dirPath
     * @param array $regionsOrder
     * @param array $toRemovePatterns
     * @param bool $onlyInfo
     */
    public function __construct(string $dirPath, array $regionsOrder, array $toRemovePatterns, bool $onlyInfo)
    {
        $this->dirPath          = $dirPath;
        $this->regionsOrder     = $regionsOrder;
        $this->toRemovePatterns = $toRemovePatterns;
        $this->onlyInfo         = $onlyInfo;
    }

    public function handle()
    {
        $fileNames = $this->getFileNames();

        $convertedFileNames = $this->getConvertedFileNames($fileNames);

        $this->scanFileNames($convertedFileNames);

        $info = $this->getInfo($convertedFileNames);

        if ($this->onlyInfo) {
            echo $info;
        } else {
            $this->deleteFiles(array_intersect_key($fileNames, array_flip($this->toRemoveKeys)));

            file_put_contents($this->dirPath . DIRECTORY_SEPARATOR . 'no_intro_roms_filter_log_' . date('YmdHis'), $info);
        }
    }

    /**
     * @param array $fileNames
     */
    protected function scanFileNames(array $fileNames)
    {
        $this->toRemoveKeys = [];
        $this->unknownKeys  = [];

        $roms            = [];
        $currentBaseName = null;

        foreach ($fileNames as $key => $fileName) {
            preg_match_all('/^([^\(]*)|\(([^\)]*)\)/u', $fileName, $matches);

            $baseName = trim(
                mb_strtolower(
                    count($matches[0]) > 1
                        ? $matches[1][0]
                        : pathinfo($fileName, PATHINFO_FILENAME)
                )
            );

            if ($baseName != $currentBaseName) {
                $romsCount = count($roms);

                if ($romsCount > 1) {
                    $toRemoveKeys = $this->getToRemoveKeys($roms);

                    $this->toRemoveKeys = array_merge($this->toRemoveKeys, $toRemoveKeys);

                    if ($this->onlyInfo && $romsCount - count($toRemoveKeys) > 1) {
                        $this->unknownKeys = array_merge(
                            $this->unknownKeys,
                            array_diff(array_keys($roms), $toRemoveKeys)
                        );
                    }
                }

                $currentBaseName = $baseName;
                $roms            = [];
            }

            $roms[$key] = new Rom($fileName, array_slice($matches[2], 1));
        }
    }

    /**
     * @param array $fileNames
     * @return array
     */
    protected function getConvertedFileNames(array $fileNames): array
    {
        return array_map(function ($fileName) {
            return iconv(mb_detect_encoding($fileName), 'UTF-8', $fileName);
        }, $fileNames);
    }

    /**
     * @return array
     */
    protected function getFileNames(): array
    {
        $fileNames = [];

        foreach (scandir($this->dirPath) as $fileName) {
            if (is_file($this->dirPath . DIRECTORY_SEPARATOR . $fileName)) {
                $fileNames[] = $fileName;
            }
        }

        return $fileNames;
    }

    /**
     * @param Rom $rom
     * @return bool
     */
    protected function isToRemoveRom(Rom $rom): bool
    {
        foreach ($rom->getParams() as $param) {
            foreach ($this->toRemovePatterns as $pattern) {
                if (preg_match("/{$pattern}/iu", $param)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param Rom[] $roms
     * @return array
     */
    protected function getBestKeys(array $roms): array
    {
        $revisionsAndVersions = [];
        $withoutParamsKeys    = [];

        foreach ($roms as $key => $rom) {
            $revision = $rom->getRevision();

            if ($revision) {
                $revisionsAndVersions['revisions'][$key] = $revision;
            } else {
                $version = $rom->getVersion();

                if ($version) {
                    $revisionsAndVersions['versions'][$key] = $version;
                }
            }

            if (!$rom->getParams()) {
                $withoutParamsKeys[] = $key;
            }
        }

        if (!$revisionsAndVersions && $withoutParamsKeys) {
            $keys = $withoutParamsKeys;
        } elseif (count($revisionsAndVersions) == 1) {
            $revisionsOrVersions  = reset($revisionsAndVersions);
            $maxRevisionOrVersion = max($revisionsOrVersions);

            $keys = array_keys($revisionsOrVersions, $maxRevisionOrVersion);
        } else {
            $keys = array_keys($roms);
        }

        return $keys;
    }

    /**
     * @param Rom[] $roms
     * @return array
     */
    protected function getUserRemovedKeys(array $roms): array
    {
        $userRemovedKeys = [];

        if (!$this->onlyInfo) {
            $indexKeys = [];
            $i         = 1;

            echo PHP_EOL;
            echo 'What do you want to do with these roms?' . PHP_EOL;
            echo PHP_EOL;

            foreach ($roms as $key => $rom) {
                $indexKeys[$i] = $key;

                echo $i . '. ' . $rom->getName() . PHP_EOL;

                $i++;
            }

            echo PHP_EOL;
            echo 'Keep all: +' . PHP_EOL;
            echo 'Remove all: -' . PHP_EOL;
            echo 'Or list of comma-separated indexes of the roms that you want to keep (for example: 2, 4)' . PHP_EOL;
            echo 'Type answer:';

            do {
                $correctAnswer = true;
                $answer        = trim(fgets(STDIN));

                switch ($answer) {
                    case '+':
                        $userRemovedKeys = [];

                        break;
                    case '-':
                        $userRemovedKeys = array_keys($roms);

                        break;
                    default:
                        $toKeepIndexes = preg_split('/ *, */u', $answer);

                        foreach ($toKeepIndexes as $index) {
                            if (
                                $index !== (string) ((int) $index)
                                || !in_array($index, array_keys($indexKeys))
                            ) {
                                $correctAnswer = false;

                                break;
                            }
                        }

                        if ($correctAnswer && count($toKeepIndexes) != count(array_unique($toKeepIndexes))) {
                            $correctAnswer = false;
                        }

                        if ($correctAnswer) {
                            $userRemovedKeys = array_values(array_diff_key($indexKeys, array_flip($toKeepIndexes)));
                        } else {
                            echo 'Wrong answer. Repeat:';
                        }
                }
            } while (!$correctAnswer);
        }

        return $userRemovedKeys;
    }

    /**
     * @param Rom[] $roms
     * @return array
     */
    protected function getToRemoveKeys(array $roms): array
    {
        $toRemoveKeys = [];

        foreach ($roms as $key => $rom) {
            if ($this->isToRemoveRom($rom)) {
                $toRemoveKeys[] = $key;
            }
        }

        $filteredRoms = array_diff_key($roms, array_flip($toRemoveKeys));

        if (count($filteredRoms) > 1) {
            $keysByRegions = [];

            foreach ($filteredRoms as $key => $rom) {
                $regions = array_map(function ($region) {
                    return mb_strtolower($region);
                }, $rom->getRegions() ?: ['']);

                foreach ($regions as $region) {
                    $keysByRegions[$region][] = $key;
                }
            }

            $keyPacks = array_values($keysByRegions);

            if (count($keysByRegions) > 1) {
                $regions    = array_keys($keysByRegions);
                $bestRegion = null;

                foreach ($this->regionsOrder as $region) {
                    if (in_array($region, $regions)) {
                        $bestRegion = $region;

                        break;
                    }
                }

                if (!is_null($bestRegion)) {
                    $keyPacks = [$keysByRegions[$bestRegion]];
                }
            }

            $bestKeys = [];

            foreach ($keyPacks as $keys) {
                $bestKeys = array_merge(
                    $bestKeys,
                    $this->getBestKeys(array_intersect_key($filteredRoms, array_flip($keys)))
                );
            }

            $bestKeys = array_unique($bestKeys);

            if (count($bestKeys) > 1) {
                $toKeepKeys = array_diff(
                    $bestKeys,
                    $this->getUserRemovedKeys(array_intersect_key($filteredRoms, array_flip($bestKeys)))
                );
            } else {
                $toKeepKeys = $bestKeys;
            }

            $toRemoveKeys = array_diff(array_keys($roms), $toKeepKeys);
        } elseif (!$filteredRoms) {
            $toRemoveKeys = $this->getUserRemovedKeys($roms);
        }

        return $toRemoveKeys;
    }

    /**
     * @param array $fileNames
     */
    protected function deleteFiles(array $fileNames)
    {
        foreach ($fileNames as $fileName) {
            unlink($this->dirPath . DIRECTORY_SEPARATOR . $fileName);
        }
    }

    /**
     * @param array $fileNames
     * @return string
     */
    protected function getInfo(array $fileNames): string
    {
        $info = '';

        foreach ($fileNames as $key => $fileName) {
            if (in_array($key, $this->toRemoveKeys)) {
                $prefix = '-';
            } elseif (in_array($key, $this->unknownKeys)) {
                $prefix = '?';
            } else {
                $prefix = ' ';
            }

            $info .= $prefix . ' ' . $fileName . PHP_EOL;
        }

        return $info;
    }
}
