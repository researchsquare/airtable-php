<?php

namespace TANIOS\Airtable;

class Response implements \ArrayAccess
{
    private $airtable;
    private $request;
    private $content = '';
    private $parsedContent = false;

    public function __construct(Airtable $airtable, Request $request, string $content, bool $relations = false)
    {
        $this->airtable = $airtable;
        $this->request = $request;
        $this->content = $content;

        try {
            $this->parsedContent = json_decode($content);
        } catch (\Exception $e) {
            $this->parsedContent = false;
        }

        if (is_array($relations) && count($relations) > 0) {
            if (array_keys($relations) !== range(0, count($relations) - 1)) {
                foreach ($relations as $relatedField => $relatedTable ) {
                    $this->processRelatedField($relatedField, $relatedTable);
                }
            } else {
                foreach ($relations as $relatedField) {
                    $this->processRelatedField($relatedField);
                }
            }

        }
    }

    private function processRelatedField(string $relatedField, bool $relatedTable = false) : void
    {
        if (isset($this->parsedContent->records) && is_array($this->parsedContent->records) && count($this->parsedContent->records) > 0) {
            foreach ($this->parsedContent->records as $recordKey => $record) {
                $this->parsedContent->records[$recordKey] = $this->loadRelatedField($relatedField, $relatedTable, $record);
            }
        } else {
            $this->parsedContent = $this->loadRelatedField($relatedField, $relatedTable, $this->parsedContent);
        }
    }

    private function loadRelatedField(string $relatedField, string $relatedTable, object $record) : object
    {
        if (!isset($record->fields) || !isset($record->fields->$relatedField)) {
            return $record;
        }

        if (empty($relatedTable)) {
            $relatedTable = $relatedField;
        }

        $relationIds = $record->fields->$relatedField;

        if (!is_array($relationIds)) {
            $relationIds = [$relationIds];
        }

        $relationFormula = "OR(";
        $relationFormula .= implode(', ', array_map(function($id) {
            return "RECORD_ID() = '$id'";
        }, $relationIds));
        $relationFormula .= ")";

        if (!is_array($relatedTable)) {
            $relationRequest = $this->airtable->getContent("$relatedTable", [
                'filterByFormula' => $relationFormula,
            ]);
        } else {
            $relatedTableRelations = isset($relatedTable['relations']) && is_array($relatedTable['relations'])
                ? $relatedTable['relations']
                : false;

            $relatedTableName = !empty($relatedTable[ 'table' ]) ? $relatedTable['table'] : $relatedField;

            $relationRequest = $this->airtable->getContent("$relatedTableName", [
                'filterByFormula' => $relationFormula,
            ], $relatedTableRelations);
        }

        $relatedRecords = [];

        do {
            $relationResponse = $relationRequest->getResponse();

            if (!is_array($relationResponse->records) || count($relationResponse->records) < 0) {
                break;
            }

            foreach ($relationResponse->records as $relatedRecord) {
                $formattedRecord = $relatedRecord->fields;
                $formattedRecord->id = $relatedRecord->id;

                $relatedRecords[] = $formattedRecord;
            }
        } while($relationRequest = $relationResponse->next());

        if (is_array($record->fields->$relatedField)) {
            $record->fields->$relatedField = $relatedRecords;
        } else {
            $record->fields->$relatedField = count($relatedRecords) > 0
                ? $relatedRecords[0]
                : null;
        }

        return $record;
    }

    public function next()
    {
        if (!$this->parsedContent) {
            return false;
        }

        if (!isset($this['offset'])) {
            return false;
        }

        $this->request->offset = $this['offset'];

        return $this->request;
    }

    public function __get(string $key) : ?string
    {
        if (!$this->parsedContent || ! isset($this->parsedContent->$key)) {
            return null;
        }

        return $this->parsedContent->$key;
    }

    public function __toString() : string
    {
        return $this->content;
    }

    public function __isset(string $key) : bool
    {
        return $this->parsedContent && isset($this->parsedContent->$key);
    }

    public function offsetExists($offset)
    {
        return $this->parsedContent && isset($this->parsedContent->$offset);
    }

    public function offsetGet($offset)
    {
        return $this->parsedContent && isset($this->parsedContent->$offset)
            ? $this->parsedContent->$offset
            : null;
    }

    public function offsetSet($offset, $value)
    {
        if ($this->parsedContent) {
            $this->parsedContent->$offset = $value;
        }
    }

    public function offsetUnset($offset)
    {
        if ($this->parsedContent && isset($this->parsedContent->$offset)) {
            unset($this->parsedContent->$offset);
        }
    }
}

