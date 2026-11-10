<?php

namespace Files\Table;

use Micro\Database\Table\TableAbstract;
use Micro\Http\UploadedFile;
use Files\Entity\File;
use Files\Helper\Files as FilesHelper;
use Micro\Cache;
use Micro\Cache\Frontend\Json as CacheJson;
use Micro\Database\Expr;

class Files extends TableAbstract
{
    protected static $dir = 'uploads/files';

    protected $_name = 'Files';

    public static function setDir($dir)
    {
        static::$dir = $dir;
    }

    public static function getDir()
    {
        return static::$dir;
    }

    public function getFileByHash($hash, array $columns)
    {
        $select = $this->createHybridSelect()
                       ->where('hash = ?', $hash)
                       ->reset('columns')
                       ->columns($columns);


        return $this->fetchRow($select);
    }

    public function add($target, $targetId, UploadedFile $file, $type = null, $lang = null, $tag = null)
    {
        $adapter = $this->getAdapter();

        try {

            $adapter->beginTransaction();

            $extenstion = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
            $hash = sha1((string) $type . (string) $lang . $file->file . uniqid(time()));

            $sort = $this->getAdapter()->fetchRow('
                SELECT IFNULL(MAX(sort), 0) + 1 as nextSort, uuid() as uuid
                FROM Files
                WHERE target = ? AND targetId = ? AND ' . ($type === null ? 'type IS NULL' : 'type = ' . $this->getAdapter()->quote($type)) . '  AND ' . ($lang === null ? 'lang IS NULL' : 'lang = ' . $this->getAdapter()->quote($lang)),
                [$target, $targetId]
            );
            $nextSort   = $sort['nextSort'];
            $uuid       = $sort['uuid'];

            $row = $this->createRow([
                'type' => $type,
                'lang' => $lang,
                'hash' => $hash,
                'uuid' => $uuid,
                'mime' => $file->type,
                'file' => $hash . '.' . $extenstion,
                'extension' => $extenstion,
                'name' => $file->getClientFilename(),
                'size' => $file->getSize(),
                'metadata' => json_encode([]),
                'sort' => $nextSort,
                'tags' => $tag
            ]);

            $row->target = $target;
            $row->targetId = (int) $targetId;

            $row->save();

            // save on hard drive
            $dir = public_path(static::getDir() . DIRECTORY_SEPARATOR . $target);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $targetPath = $dir . DIRECTORY_SEPARATOR . $hash . '.' . $extenstion;

            if (!is_writable(dirname($targetPath))) {
                throw new \InvalidArgumentException('Upload target path is not writable');
            }

            if (!copy($file->file, $targetPath)) {
                throw new \RuntimeException(sprintf('Error moving uploaded file %1s to %2s', $file->getClientFilename(), $targetPath));
            }

            $this->removeCache();

            $adapter->commit();

        } catch (\Exception $e) {
            $adapter->rollBack();
            throw $e;
        }

        return $row;
    }

    public function addLink($target, $targetId, $link, $type = null, $lang = null, $tag = null)
    {
        $adapter = $this->getAdapter();

        try {

            $adapter->beginTransaction();


            $hash = sha1((string) $type . (string) $lang . $link . uniqid(time()));

            $sort = $this->getAdapter()->fetchRow('
                SELECT IFNULL(MAX(sort), 0) + 1 as nextSort, uuid() as uuid
                FROM Files
                WHERE target = ? AND targetId = ? AND ' . ($type === null ? 'type IS NULL' : 'type = ' . $this->getAdapter()->quote($type)) . '  AND ' . ($lang === null ? 'lang IS NULL' : 'lang = ' . $this->getAdapter()->quote($lang)),
                [$target, $targetId]
            );
            $nextSort   = $sort['nextSort'];
            $uuid       = $sort['uuid'];

            $mime = FilesHelper::checkLinkType($link);

            $row = $this->createRow([
                'type' => $type,
                'lang' => $lang,
                'hash' => $hash,
                'uuid' => $uuid,
                'mime' => $mime['type'].'/'.$mime['media'],
                'file' => null,
                'extension' => $mime['media'],
                'name' => $link,
                'size' => null,
                'metadata' => json_encode([]),
                'sort' => $nextSort,
                'tags' => $tag
            ]);

            $row->target = $target;
            $row->targetId = (int) $targetId;

            $row->save();


            $this->removeCache();

            $adapter->commit();

        } catch (\Exception $e) {
            $adapter->rollBack();
            throw $e;
        }

        return $row;
    }

    public function getFilesByTarget($target, $type = null, $lang = null)
    {
        $files = $this->getFiles($target, null, $type, $lang);

        $result = [];

        foreach ($files as $file) {
            if (!isset($result[$file['targetId']])) {
                $result[$file['targetId']] = [];
            }
            $result[$file['targetId']][] = $file;
        }

        return $result;
    }

    public function getFiles($target, $targetId = null, $type = null, $lang = null, $tag = null, array $params = [])
    {
        $cols = $this->info('cols');

        unset($cols[array_search('blob', $cols)]);

        $select = $this->select(true)
                       ->where('target = ?', $target)
                       ->reset('columns')->columns($cols);

        if (isset($params['order'])) {
            $select->order($params['order']);
        } else {
            $select->order('sort ASC');
        }

        if ($targetId !== null) {
            $select->where('targetId = ?', (int) $targetId);
        }

        if ($type !== null) {
            $select->where('type = ?', $type);
        }

        if ($lang !== null) {
            $select->where('lang = ?', $lang);        }

        if ($tag != null) {
            $select->where(new Expr("FIND_IN_SET('$tag', tags)"));        }

        $files = $this->getAdapter()->fetchAll($select);

        $result = [];

        foreach ($files as $k => $v) {
            $v['metadata'] = json_decode($v['metadata'], true);
            $file = new File();
            $file->setFromArray($v);
            $result[] = $file;
        }

        return $result;
    }

    public function removeByTarget($target, $targetId, $type = null, $lang = null)
    {
        $files = $this->getFiles($target, $targetId, $type, $lang);

        $results = [];

        foreach ($files as $file) {
            $results[] = $this->remove($file['id']);
        }

        return $results;
    }

    public function remove($fileRow)
    {
        $file = is_object($fileRow) ? $fileRow : $this->find((int) $fileRow)->current();

        $result = [];

        if ($file) {

            $result = $file->toArray();

            $file->delete();

            $dir = static::getDir() . DIRECTORY_SEPARATOR . $result['target'];

            @unlink(public_path($dir . DIRECTORY_SEPARATOR . $result['file']));

            if (is_array($thumbs = glob(public_path($dir . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . '*_' . $result['file'])))) {
                foreach ($thumbs as $thumb) {
                    @unlink($thumb);
                }
            }

            $this->removeCache();
        }

        return $result;
    }

    public function updateMetadata($fileId, array $data, $tags = null)
    {
        $file = $this->find((int) $fileId)->current();

        $result = [];

        if ($file) {

            $metadata = json_decode($file->metadata, true);
            $metadata = !is_array($metadata) ? [] : $metadata;

            $file->metadata = json_encode(array_merge($metadata, $data));

          //  if($tags) {
                $file->tags = $tags;
          //  }

            $file->save();

            $this->removeCache();

            $result = $file->toArray();
        }

        return $result;
    }

    public function removeCache()
    {
        $cache = cache('json');
        if ($cache instanceof CacheJson) {
            $cache->clean(Cache\Cache::CLEANING_MODE_MATCHING_TAG, ['FilesTargets']);
        }
    }

    public function cacheTargetsTable($target = null)
    {
        $cache = cache('json');

        $cacheId = 'FilesTargets';

        if ($target !== null) {
            $cacheId .= '_' . $target;
        }

        if (!$cache instanceof CacheJson || (($item = $cache->load($cacheId)) === \false)) {

            $where = null;

            if ($target !== null) {
                $where['target = ?'] = $target;
            }

            $rows = $this->fetchAll($where, 'sort ASC');

            $item = [];

            if ($rows) {

                foreach ($rows as $row) {

                   if (!isset($item[$row['target']])) {
                       $item[$row['target']] = [];
                   }

                   if (!isset($item[$row['target']][$row['targetId']])) {
                       $item[$row['target']][$row['targetId']] = [];
                   }

                   if (!isset($item[$row['target']][$row['targetId']][$row['type']])) {
                       $item[$row['target']][$row['targetId']][$row['type']] = [];
                   }

                   $data = $row->toArray();

                   $data['metadata'] = $data['metadata']
                                       ? json_decode($data['metadata'], true)
                                       : [];

                   $item[$row['target']][$row['targetId']][$row['type']][] = $data;
                }
            }

            if ($cache instanceof CacheJson) {
                $cache->save($item, $cacheId, ['FilesTargets']);
            }
        }

        return $item;
    }

    public function getTargetsTable($target = null)
    {
        static $cache;

        $cacheId = $target === null ? '__All_Targets' : $target;

        if (isset($cache[$cacheId])) {
            return $cache[$cacheId];
        }

        $files = $this->cacheTargetsTable($target);

        foreach ($files as $i => $target) {
            foreach ($target as $j => $targetId) {
                foreach ($targetId as $k => $type) {
                    foreach ($type as $p => $fileArr) {
                        $files[$i][$j][$k][$p] = $fileArr;
                    }
                }
            }
        }

        return $cache[$cacheId] = $files;
    }

    public function getFilesForTarget($target, $type, $asObject = true)
    {
        $targets = $this->getTargetsTable($target);

        if (!isset($targets[$target])) {
            return [];
        }

        $result = [];

        foreach ($targets[$target] as $j => $types) {
            foreach ($types as $k => $files) {
                if ($k == $type) {
                    if ($asObject) {
                        foreach ($files as $fileId => $fileArr) {
                            $files[$fileId] = (new File())->setFromArray($fileArr);
                        }
                    }
                    $result[$j] = $files;
                }
            }
        }

        return $result;
    }

    public function getFilesForTargetId($target, $targetId, $type, $asObject = true, $lang = null, $tags = [])
    {
        $targets = $this->getTargetsTable($target);

        if (!isset($targets[$target])
            || !isset($targets[$target][$targetId])
            || !isset($targets[$target][$targetId][$type])
        ) {
            return [];
        }

        $files = $targets[$target][$targetId][$type];
        $response = [];
        if ($asObject) {
            foreach ($files as $k => $v) {
                //Find by tags
                $fileTags =  explode(",", $v['tags']);
                  if(!empty($tags) && is_array($fileTags) && !array_intersect($tags,$fileTags)) {
                    continue;
                }

                if ($lang) {
                    if ($v['lang'] == $lang) {
                       $response[] = (new File())->setFromArray($v);
                    } else {
                       // unset($files[$k]);
                    }
                } else {
                    $response[] = (new File())->setFromArray($v);
                }
            }
        }

        return $response;
    }
}