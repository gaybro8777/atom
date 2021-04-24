<?php

use org\bovigo\vfs\vfsStream;

/**
 * @internal
 * @coversNothing
 */
class CsvImportValidatorTest extends \PHPUnit\Framework\TestCase
{
    protected $vdbcon;
    protected $context;

    public function setUp(): void
    {
        $this->context = sfContext::getInstance();
        $this->vdbcon = $this->createMock(DebugPDO::class);

        $this->csvHeader = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';

        $this->csvHeaderWithDigitalObjectCols = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,digitalObjectPath,digitalObjectUri,culture';

        $this->csvHeaderWithEventType = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,eventTypes,eventDates,eventStartDates,eventEndDates,repository,culture';
        $this->csvHeaderWithAllEventCols = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,eventTypes,eventDates,eventStartDates,eventEndDates,eventActors,eventActorHistories,eventPlaces,repository,culture';

        $this->csvHeaderWithLanguage = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture,language';

        $this->csvHeaderUnknownColumnName = 'legacyId,parentId,identifier,title,levilOfDescrooption,extentAndMedium,repository,culture';
        $this->csvHeaderBadCaseColumnName = 'legacyId,parentId, identifier,Title,levelOfDescription,extentAndMedium,repository,culture';

        $this->csvHeaderShort = 'legacyId,parentId,identifier,title,levelOfDescription,repository,culture';
        $this->csvHeaderLong = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture,extraHeading';

        $this->csvHeaderMissingParentId = 'legacyId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderMissingLegacyId = 'parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderMissingParentIdLegacyId = 'identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderMissingCulture = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository';
        $this->csvHeaderWithLanguage = 'legacyId,parentId,identifier,title,levelOfDescription,extentAndMedium,repository,culture,language';

        $this->csvHeaderDuplicatedRepository = 'legacyId,parentId,identifier,title,repository,extentAndMedium,repository,culture';
        $this->csvHeaderDuplicatedRepositoryCulture = 'legacyId,parentId,culture,title,repository,culture,repository,culture';

        $this->csvHeaderWithQubitParentSlug = 'legacyId,qubitParentSlug,identifier,title,levelOfDescription,extentAndMedium,repository,culture';
        $this->csvHeaderWithParentIdQubitParentSlug = 'legacyId,parentId,qubitParentSlug,identifier,title,levelOfDescription,extentAndMedium,repository,culture';

        $this->csvHeaderBlank = '';
        $this->csvHeaderBlankWithCommas = ',,,';

        $this->csvHeaderWithUtf8Bom = CsvImportValidator::UTF8_BOM.$this->csvHeader;
        $this->csvHeaderWithUtf16LEBom = CsvImportValidator::UTF16_LITTLE_ENDIAN_BOM.$this->csvHeader;
        $this->csvHeaderWithUtf16BEBom = CsvImportValidator::UTF16_BIG_ENDIAN_BOM.$this->csvHeader;
        $this->csvHeaderWithUtf32LEBom = CsvImportValidator::UTF32_LITTLE_ENDIAN_BOM.$this->csvHeader;
        $this->csvHeaderWithUtf32BEBom = CsvImportValidator::UTF32_BIG_ENDIAN_BOM.$this->csvHeader;

        $this->csvData = [
            // Note: leading and trailing whitespace in first row is intentional
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataWithDigitalObjectCols = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","","",""',
            '"","","","Chemise","","","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "","","", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "","","", "en"',
        ];

        $this->csvDataWithEventType = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","creation","1922-1925","1922","1925","",""',
            '"","","","Chemise","","","creation","2010","01-01-2010","12-12-2010","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "creation","2020-2021","Jan 1, 2020","Dec 31 2021", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","","creation", "1900-1999",1900,1999, "", "en"',
        ];

        $this->csvDataWithEventTypeMismatches = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","creation","1922-1925",,"1925","",""',
            '"","","","Chemise","","","creation|donation","2010","01-01-2010","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", ,"2020-2021","Jan 1, 2020","Dec 31 2021", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","","creation", "1900-1999",1900,1999, "", "en"',
        ];

        $this->csvDataWithAllEventCols = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","creation","1922-1925","1922","1925",S. Smith,Smith history., Chilliwack, BC,"",""',
            '"","","","Chemise","","","creation","2010","01-01-2010","12-12-2010",,,,"","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "creation","2020-2021","Jan 1, 2020","Dec 31 2021",,,, "", ""',
            '"", "DJ003", "ID4", "Title Four", "","","creation|donation", "1900|1999",1900|1901,1999|2000,,,, "", "en"',
        ];

        $this->csvDataDuplicatedLegacyId = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"B10101", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataMissingParentId = [
            '"B10101 ","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","Chemise","","","","fr"',
            '"D20202", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataMissingLegacyId = [
            '" DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","Chemise","","","","fr"',
            '"DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataMissingParentIdLegacyId = [
            '"ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","Chemise","","","","fr"',
            '"", "Voûte, étagère 0074", "", "", "", ""',
            '"ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataMissingCulture = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1",""',
            '"","","","Chemise","","",""',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", ""',
        ];

        $this->csvDataValidCultures = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es "',
            '"","","","Chemise","","","","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "de"',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataValidLanguages = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es ", "es"',
            '"","","","Chemise","","","","fr","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "de","en "',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"," en"',
        ];

        $this->csvDataLanguagesSomeInvalid = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es ", "Spanish"',
            '"","","","Chemise","","","","fr","fr|en"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "de","en_GB"',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"," en_gb"',
        ];

        $this->csvDataCultureLanguage = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es ","es"',
            '"","","","Chemise","","","","fr","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "de","de"',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en","english"',
        ];

        $this->csvDataCultureLanguageMultErrors = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es ","this is spanish"',
            '"","","","Chemise","","","","fr","fr"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "Germany","de"',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en","english"',
        ];

        $this->csvDataCulturesSomeInvalid = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","es "',
            '"","","","Chemise","","","","fr|en"',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", "gg"',
            '"E20202", "DJ003", "ID4", "Title Four", "","", "", "en"',
            '"F20202", "DJ004", "DD8989", "pdf documents", "","", "", ""',
        ];

        $this->csvDataParentIdColumnEmpty = [
            '"B10101 "," ","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "", "", "Voûte, étagère 0074", "", "", "", ""',
            '"X7", "", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataParentIdMatches = [
            '"B10101 "," ","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "B10101 ", "", "Voûte, étagère 0074", "", "", "", ""',
            '"X7", "", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataParentIdMatchesInKeymap = [
            '"B10101 "," ","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise","","","","fr"',
            '"D20202", "A10101 ", "", "Voûte, étagère 0074", "", "", "", ""',
            '"X7", "", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataQubitParentSlug = [
            '"B10101 "," ","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"C10101","","","Chemise","","","","fr"',
            '"D20202", "parent-slug", "", "Voûte, étagère 0074", "", "", "", ""',
            '"X7", "", "ID4", "Title Four", "","", "", "en"',
            '"X7", "missing-slug", "TY99", "Some stuff", "","", "", "en"',
        ];

        $this->csvDataParentIdAndQubitParentSlug = [
            '"B10101 ", "", " ","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"C10101","B10101","","","Chemise","","","","fr"',
            '"D20202", "C10101", "parent-slug", "", "Voûte, étagère 0074", "", "", "", ""',
            '"X7","", "parent-slug-again", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataShortRow = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise ","","","fr"',  // Short row: 7 cols
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataShortRows = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise ","","","fr"',  // Short row: 7 cols
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "", "en"',  // Short row: 6 cols
            '', // Short row: zero cols
        ];

        $this->csvDataLongRow = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '"","","","Chemise ","","", "","fr", ""',  // Long row: 9 cols
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataLongRows = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","","","","",', // Long row: 12 cols
            '"","","","Chemise ","","", "","fr", ""',  // Long row: 9 cols
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        $this->csvDataEmptyRows = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            '',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
            '  , ',
            ' ',
            '',
        ];

        $this->csvDataEmptyRowsWithCommas = [
            '"B10101 "," DJ001","ID1 ","Some Photographs","","Extent and medium 1","",""',
            ',,,',
            '"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""',
            '   , , ',
            '"", "DJ003", "ID4", "Title Four", "","", "", "en"',
        ];

        // define virtual file system
        $directory = [
            'unix_csv_with_utf8_bom.csv' => $this->csvHeaderWithUtf8Bom."\n".implode("\n", $this->csvData),
            'unix_csv_without_utf8_bom.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),
            'windows_csv_with_utf8_bom.csv' => $this->csvHeaderWithUtf8Bom."\r\n".implode("\r\n", $this->csvData),
            'windows_csv_without_utf8_bom.csv' => $this->csvHeader."\r\n".implode("\r\n", $this->csvData),
            'unix_csv-windows_1252.csv' => mb_convert_encoding($this->csvHeader."\n".implode("\n", $this->csvData), 'Windows-1252', 'UTF-8'),
            'windows_csv-windows_1252.csv' => mb_convert_encoding($this->csvHeader."\r\n".implode("\r\n", $this->csvData), 'Windows-1252', 'UTF-8'),
            'unix_csv_with_utf16LE_bom.csv' => $this->csvHeaderWithUtf16LEBom."\n".implode("\n", $this->csvData),
            'unix_csv_with_utf16BE_bom.csv' => $this->csvHeaderWithUtf16BEBom."\n".implode("\n", $this->csvData),
            'unix_csv_with_utf32LE_bom.csv' => $this->csvHeaderWithUtf32LEBom."\n".implode("\n", $this->csvData),
            'unix_csv_with_utf32BE_bom.csv' => $this->csvHeaderWithUtf32BEBom."\n".implode("\n", $this->csvData),
            'unix_csv_with_short_header.csv' => $this->csvHeaderShort."\n".implode("\n", $this->csvData),
            'unix_csv_with_long_header.csv' => $this->csvHeaderLong."\n".implode("\n", $this->csvData),
            'unix_csv_with_short_row.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataShortRow),
            'unix_csv_with_long_row.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataLongRow),
            'unix_csv_with_short_rows.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataShortRows),
            'unix_csv_with_long_rows.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataLongRows),
            'unix_csv_with_empty_rows.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataEmptyRows),
            'unix_csv_with_empty_rows_with_commas.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataEmptyRowsWithCommas),
            'unix_csv_with_empty_rows_header.csv' => $this->csvHeaderBlank."\n".implode("\n", $this->csvDataEmptyRows),
            'unix_csv_with_empty_rows_header_with_commas.csv' => $this->csvHeaderBlankWithCommas."\n".implode("\n", $this->csvDataEmptyRowsWithCommas),
            'unix_csv_missing_parent_id.csv' => $this->csvHeaderMissingParentId."\n".implode("\n", $this->csvDataMissingParentId),
            'unix_csv_missing_legacy_id.csv' => $this->csvHeaderMissingLegacyId."\n".implode("\n", $this->csvDataMissingLegacyId),
            'unix_csv_missing_parent_id_legacy_id.csv' => $this->csvHeaderMissingParentIdLegacyId."\n".implode("\n", $this->csvDataMissingParentIdLegacyId),
            'unix_csv_parent_id_column_empty.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataParentIdColumnEmpty),
            'unix_csv_parent_id_matches.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataParentIdMatches),
            'unix_csv_parent_id_matches_in_keymap.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataParentIdMatchesInKeymap),
            'unix_csv_qubit_parent_slug.csv' => $this->csvHeaderWithQubitParentSlug."\n".implode("\n", $this->csvDataQubitParentSlug),
            'unix_csv_parent_id_and_qubit_parent_slug.csv' => $this->csvHeaderWithParentIdQubitParentSlug."\n".implode("\n", $this->csvDataParentIdAndQubitParentSlug),
            'unix_csv_with_duplicated_legacy_id.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataDuplicatedLegacyId),
            'unix_csv_missing_culture.csv' => $this->csvHeaderMissingCulture."\n".implode("\n", $this->csvDataMissingCulture),
            'unix_csv_valid_cultures.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataValidCultures),
            'unix_csv_cultures_some_invalid.csv' => $this->csvHeader."\n".implode("\n", $this->csvDataCulturesSomeInvalid),
            'unix_csv_valid_languages.csv' => $this->csvHeaderWithLanguage."\n".implode("\n", $this->csvDataValidLanguages),
            'unix_csv_languages_some_invalid.csv' => $this->csvHeaderWithLanguage."\n".implode("\n", $this->csvDataLanguagesSomeInvalid),
            'unix_csv_culture_language_length_error.csv' => $this->csvHeaderWithLanguage."\n".implode("\n", $this->csvDataCultureLanguage),
            'unix_csv_culture_language_length_errors.csv' => $this->csvHeaderWithLanguage."\n".implode("\n", $this->csvDataCultureLanguageMultErrors),
            'unix_csv_one_duplicated_header.csv' => $this->csvHeaderDuplicatedRepository."\n".implode("\n", $this->csvData),
            'unix_csv_duplicated_headers.csv' => $this->csvHeaderDuplicatedRepositoryCulture."\n".implode("\n", $this->csvData),
            'unix_csv_unknown_column_name.csv' => $this->csvHeaderUnknownColumnName."\n".implode("\n", $this->csvData),
            'unix_csv_bad_case_column_name.csv' => $this->csvHeaderBadCaseColumnName."\n".implode("\n", $this->csvData),
            'unix_csv_with_event_type.csv' => $this->csvHeaderWithEventType."\n".implode("\n", $this->csvDataWithEventType),
            'unix_csv_with_event_type_mismatches.csv' => $this->csvHeaderWithEventType."\n".implode("\n", $this->csvDataWithEventTypeMismatches),
            'unix_csv_with_event_type_all_cols.csv' => $this->csvHeaderWithAllEventCols."\n".implode("\n", $this->csvDataWithAllEventCols),
            'unix_csv_with_digital_object_cols.csv' => $this->csvHeaderWithDigitalObjectCols."\n".implode("\n", $this->csvDataWithDigitalObjectCols),
            'root.csv' => $this->csvHeader."\n".implode("\n", $this->csvData),
            'digital_objects' => [
                'a.png' => random_bytes(100),
                'b.png' => random_bytes(100),
                'c.png' => random_bytes(100),
            ],
        ];

        $this->vfs = vfsStream::setup('root', null, $directory);

        $file = $this->vfs->getChild('root/root.csv');
        $file->chmod('0400');
        $file->chown(vfsStream::OWNER_ROOT);

        $this->ormClasses = [
            'QubitFlatfileImport' => \AccessToMemory\test\mock\QubitFlatfileImport::class,
            'QubitObject' => \AccessToMemory\test\mock\QubitObject::class,
        ];
    }

    // Data providers

    public function setOptionsProvider()
    {
    }

    // Basic tests

    public function testConstructorWithNoContextPassed()
    {
        $csvValidator = new CsvImportValidator(null, $this->vdbcon, null);

        $this->assertSame(sfContext::class, get_class($csvValidator->getContext()));
    }

    public function testConstructorWithNoDbconPassed()
    {
        $csvValidator = new CsvImportValidator($this->context, null, null);

        $this->assertSame(DebugPDO::class, get_class($csvValidator->getDbCon()));
    }

    public function testSetInvalidOptionsException()
    {
        $this->expectException(UnexpectedValueException::class);
        $options = ['fakeOption'];
        $csvValidator = new CsvImportValidator($this->context, null, $options);
    }

    public function testSetValidClassNameOption()
    {
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('className', 'QubitInformationObject');
        $this->assertSame('QubitInformationObject', $csvValidator->getOption('className'));
    }

    public function testSetInvalidClassNameOption()
    {
        $this->expectException(UnexpectedValueException::class);
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('className', 'QubitProperty');
    }

    public function testSetValidVerboseTypeOption()
    {
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('verbose', true);
        $this->assertSame(true, $csvValidator->getOption('verbose'));
    }

    public function testSetSourceOption()
    {
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('source', 'testfilename.csv');
        $this->assertSame('testfilename.csv', $csvValidator->getOption('source'));
    }

    public function testSetSeparatorOption()
    {
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('separator', ';');
        $this->assertSame(';', $csvValidator->getOption('separator'));
    }

    public function testSetInvalidSeparatorOption()
    {
        $this->expectException(UnexpectedValueException::class);
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('separator', ';;');
    }

    public function testSetEnclosureOption()
    {
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('enclosure', "'");
        $this->assertSame("'", $csvValidator->getOption('enclosure'));
    }

    public function testSetInvalidEnclosureOption()
    {
        $this->expectException(UnexpectedValueException::class);
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('enclosure', '""');
    }

    public function testSetSpecificTestsOption()
    {
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('specificTests', 'CsvSampleValuesTest,CsvLegacyIdTest');
        $this->assertSame('CsvSampleValuesTest,CsvLegacyIdTest', $csvValidator->getOption('specificTests'));
    }

    public function testSetPathToDigitalObjectsOption()
    {
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $csvValidator->setOption('pathToDigitalObjects', '/usr/test/example');
        $this->assertSame('/usr/test/example', $csvValidator->getOption('pathToDigitalObjects'));
    }

    public function testDefaultOptions()
    {
        $csvValidator = new CsvImportValidator($this->context, null, null);
        $this->assertSame(false, $csvValidator->getOption('verbose'));
        $this->assertSame('QubitInformationObject', $csvValidator->getOption('className'));
        $this->assertSame('', $csvValidator->getOption('source'));
        $this->assertSame(',', $csvValidator->getOption('separator'));
        $this->assertSame('"', $csvValidator->getOption('enclosure'));
        $this->assertSame('', $csvValidator->getOption('specificTests'));
        $this->assertSame('', $csvValidator->getOption('pathToDigitalObjects'));
    }

    /**
     * @dataProvider csvValidatorTestProvider
     *
     * Generic test - options and expected results from csvValidatorTestProvider()
     *
     * @param mixed $options
     */
    public function testCsvValidator($options)
    {
        $filename = $this->vfs->url().$options['filename'];
        $validatorOptions = isset($options['validatorOptions']) ? $options['validatorOptions'] : null;

        if (isset($validatorOptions['pathToDigitalObjects'])) {
            $validatorOptions['pathToDigitalObjects'] = $this->vfs->url().$validatorOptions['pathToDigitalObjects'];
        }

        $csvValidator = new CsvImportValidator($this->context, null, $validatorOptions);
        $this->runValidator($csvValidator, $filename, $options['csvValidatorClasses']);
        $result = $csvValidator->getResultsByFilenameTestname($filename, $options['testname']);

        $this->assertSame($options[CsvBaseTest::TEST_TITLE], $result[CsvBaseTest::TEST_TITLE]);
        $this->assertSame($options[CsvBaseTest::TEST_STATUS], $result[CsvBaseTest::TEST_STATUS]);
        $this->assertSame($options[CsvBaseTest::TEST_RESULTS], $result[CsvBaseTest::TEST_RESULTS]);
        $this->assertSame($options[CsvBaseTest::TEST_DETAIL], $result[CsvBaseTest::TEST_DETAIL]);
    }

    public function csvValidatorTestProvider()
    {
        $vfsUrl = 'vfs://root';

        return [
            // Test csvFileEncodingTest.class.php results.
            [
                'CsvFileEncodingTest-Utf8ValidatorUnixWithBOM' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/unix_csv_with_utf8_bom.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a UTF-8 BOM.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingTest-testUtf8ValidatorUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingTest-testUtf8ValidatorWindowsWithBOM' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/windows_csv_with_utf8_bom.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a UTF-8 BOM.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingTest-testUtf8ValidatorWindows' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/windows_csv_without_utf8_bom.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingTest-testUtf8IncompatibleUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/unix_csv-windows_1252.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding does not appear to be UTF-8 compatible.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [implode(',', str_getcsv(mb_convert_encoding('"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""', 'Windows-1252', 'UTF-8')))],
                ],
            ],

            [
                'CsvFileEncodingTest-testUtf8IncompatibleWindows' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/windows_csv-windows_1252.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding does not appear to be UTF-8 compatible.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [implode(',', str_getcsv(mb_convert_encoding('"D20202", "DJ002", "", "Voûte, étagère 0074", "", "", "", ""', 'Windows-1252', 'UTF-8')))],
                ],
            ],

            [
                'CsvFileEncodingTest-testDetectUtf16LEBomUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/unix_csv_with_utf16LE_bom.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a unicode BOM, but it is not UTF-8.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingTest-testDetectUtf16BEBomUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/unix_csv_with_utf16BE_bom.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a unicode BOM, but it is not UTF-8.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingTest-testDetectUtf32LEBomUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/unix_csv_with_utf32LE_bom.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a unicode BOM, but it is not UTF-8.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvFileEncodingTest-testDetectUtf32BEBomUnix' => [
                    'csvValidatorClasses' => ['CsvFileEncodingTest' => CsvFileEncodingTest::class],
                    'filename' => '/unix_csv_with_utf32BE_bom.csv',
                    'testname' => 'CsvFileEncodingTest',
                    CsvBaseTest::TEST_TITLE => CsvFileEncodingTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFileEncodingTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'File encoding is UTF-8 compatible.',
                        'This file includes a unicode BOM, but it is not UTF-8.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            /*
             * Test CsvSampleValuesTest.class.php
             *
             * CSV Sample Values test. Outputs column names and a sample value from first
             * populated row found. Only populated columns are included.
             */
            [
                'CsvSampleValuesTest-testSampleValues' => [
                    'csvValidatorClasses' => ['CsvSampleValuesTest' => CsvSampleValuesTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvSampleValuesTest',
                    CsvBaseTest::TEST_TITLE => CsvSampleValuesTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvSampleValuesTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'legacyId:  B10101',
                        'parentId:  DJ001',
                        'identifier:  ID1',
                        'title:  Some Photographs',
                        'extentAndMedium:  Extent and medium 1',
                        'culture:  fr',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            /*
             * Test csvColumnCountTest.class.php
             *
             * Test that all rows including header have the same number of
             * columns/elements.
             *
             * - test columns all equal length
             * - test incorrect separator set
             * - test header too short
             * - test header too long
             * - test single row too short
             * - test single row too long
             * - test rows too short
             * - test rows too long
             */
            [
                'CsvColumnCountTest-testColumnsEqualLength' => [
                    'csvValidatorClasses' => ['CsvColumnCountTest' => CsvColumnCountTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvColumnCountTest',
                    CsvBaseTest::TEST_TITLE => CsvColumnCountTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnCountTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of columns in CSV: 8',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvColumnCountTest-incorrectSeparator ' => [
                    'csvValidatorClasses' => ['CsvColumnCountTest' => CsvColumnCountTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvColumnCountTest',
                    'validatorOptions' => ['separator' => 'j'],
                    CsvBaseTest::TEST_TITLE => CsvColumnCountTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnCountTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of columns in CSV: 1',
                        'CSV appears to have only one column - check CSV separator option matches file.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvColumnCountTest-testHeaderTooShort' => [
                    'csvValidatorClasses' => ['CsvColumnCountTest' => CsvColumnCountTest::class],
                    'filename' => '/unix_csv_with_short_header.csv',
                    'testname' => 'CsvColumnCountTest',
                    CsvBaseTest::TEST_TITLE => CsvColumnCountTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnCountTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of rows with 7 columns: 1',
                        'Number of rows with 8 columns: 4',
                        'CSV rows with different lengths detected - check CSV enclosure option matches file.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvColumnCountTest-testHeaderTooLong' => [
                    'csvValidatorClasses' => ['CsvColumnCountTest' => CsvColumnCountTest::class],
                    'filename' => '/unix_csv_with_long_header.csv',
                    'testname' => 'CsvColumnCountTest',
                    CsvBaseTest::TEST_TITLE => CsvColumnCountTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnCountTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of rows with 9 columns: 1',
                        'Number of rows with 8 columns: 4',
                        'CSV rows with different lengths detected - check CSV enclosure option matches file.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvColumnCountTest-testRowTooShort' => [
                    'csvValidatorClasses' => ['CsvColumnCountTest' => CsvColumnCountTest::class],
                    'filename' => '/unix_csv_with_short_row.csv',
                    'testname' => 'CsvColumnCountTest',
                    CsvBaseTest::TEST_TITLE => CsvColumnCountTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnCountTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of rows with 8 columns: 4',
                        'Number of rows with 7 columns: 1',
                        'CSV rows with different lengths detected - check CSV enclosure option matches file.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvColumnCountTest-testRowTooLong' => [
                    'csvValidatorClasses' => ['CsvColumnCountTest' => CsvColumnCountTest::class],
                    'filename' => '/unix_csv_with_long_row.csv',
                    'testname' => 'CsvColumnCountTest',
                    CsvBaseTest::TEST_TITLE => CsvColumnCountTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnCountTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of rows with 8 columns: 4',
                        'Number of rows with 9 columns: 1',
                        'CSV rows with different lengths detected - check CSV enclosure option matches file.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvColumnCountTest-testRowsTooShort' => [
                    'csvValidatorClasses' => ['CsvColumnCountTest' => CsvColumnCountTest::class],
                    'filename' => '/unix_csv_with_short_rows.csv',
                    'testname' => 'CsvColumnCountTest',
                    CsvBaseTest::TEST_TITLE => CsvColumnCountTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnCountTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of rows with 8 columns: 3',
                        'Number of rows with 7 columns: 1',
                        'Number of rows with 6 columns: 1',
                        'CSV rows with different lengths detected - check CSV enclosure option matches file.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvColumnCountTest-testRowsTooLong' => [
                    'csvValidatorClasses' => ['CsvColumnCountTest' => CsvColumnCountTest::class],
                    'filename' => '/unix_csv_with_long_rows.csv',
                    'testname' => 'CsvColumnCountTest',
                    CsvBaseTest::TEST_TITLE => CsvColumnCountTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnCountTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of rows with 8 columns: 3',
                        'Number of rows with 11 columns: 1',
                        'Number of rows with 9 columns: 1',
                        'CSV rows with different lengths detected - check CSV enclosure option matches file.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            /*
             * Test csvEmptyRowTest.class.php
             *
             * Test if the header or any rows are empty.
             *
             */
            [
                'CsvEmptyRowTest-testNoEmptyRows' => [
                    'csvValidatorClasses' => ['CsvEmptyRowTest' => CsvEmptyRowTest::class],
                    'filename' => '/unix_csv_with_long_rows.csv',
                    'testname' => 'CsvEmptyRowTest',
                    CsvBaseTest::TEST_TITLE => CsvEmptyRowTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvEmptyRowTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'CSV does not have any blank rows.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [],
                ],
            ],

            [
                'CsvEmptyRowTest-testEmptyRows' => [
                    'csvValidatorClasses' => ['CsvEmptyRowTest' => CsvEmptyRowTest::class],
                    'filename' => '/unix_csv_with_empty_rows.csv',
                    'testname' => 'CsvEmptyRowTest',
                    CsvBaseTest::TEST_TITLE => CsvEmptyRowTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvEmptyRowTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'CSV blank row count: 2',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'Blank row numbers: 3, 6',
                    ],
                ],
            ],

            [
                'CsvEmptyRowTest-testEmptyRowsWithCommas' => [
                    'csvValidatorClasses' => ['CsvEmptyRowTest' => CsvEmptyRowTest::class],
                    'filename' => '/unix_csv_with_empty_rows_with_commas.csv',
                    'testname' => 'CsvEmptyRowTest',
                    CsvBaseTest::TEST_TITLE => CsvEmptyRowTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvEmptyRowTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'CSV blank row count: 2',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'Blank row numbers: 3, 5',
                    ],
                ],
            ],

            [
                'CsvEmptyRowTest-testEmptyHeader' => [
                    'csvValidatorClasses' => ['CsvEmptyRowTest' => CsvEmptyRowTest::class],
                    'filename' => '/unix_csv_with_empty_rows_header.csv',
                    'testname' => 'CsvEmptyRowTest',
                    CsvBaseTest::TEST_TITLE => CsvEmptyRowTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvEmptyRowTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'CSV Header is blank.',
                        'CSV blank row count: 2',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'Blank row numbers: 3, 6',
                    ],
                ],
            ],

            [
                'CsvEmptyRowTest-EmptyRowsAndHeader' => [
                    'csvValidatorClasses' => ['CsvEmptyRowTest' => CsvEmptyRowTest::class],
                    'filename' => '/unix_csv_with_empty_rows_header_with_commas.csv',
                    'testname' => 'CsvEmptyRowTest',
                    CsvBaseTest::TEST_TITLE => CsvEmptyRowTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvEmptyRowTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'CSV Header is blank.',
                        'CSV blank row count: 2',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'Blank row numbers: 3, 5',
                    ],
                ],
            ],

            /*
             * Test CsvParentTest.class.php
             *
             * Tests:
             * - parentId col missing
             * - legacyId col missing
             * - parentId not populated
             * - parentId populated - matches legacyId in file - source option populated
             * - parentId populated - matches legacyId in file - source field not populated
             * - parentId populated - matches in keymap table - source option populated
             * - parentId populated - matches in keymap table - source field not populated
             * - parentId populated - no match
             * - qubitParentSlug not populated
             * - qubitParentSlug populated - no match
             * - qubitParentSlug populated - matches db
             * - parentId and qubitParentSlug not populated
             * - parentId and qubitParentSlug both populated and matching
             * - parentId and qubitParentSlug both populated no match
             */
            [
                'CsvParentTest-ParentIdColumnMissing' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_missing_parent_id.csv',
                    'testname' => 'CsvParentTest',
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        "'parentId' and 'qubitParentSlugColumnPresent' columns not present. CSV contents will be imported as top level records.",
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvParentTest-LegacyIdColumnMissing' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_missing_legacy_id.csv',
                    'testname' => 'CsvParentTest',
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with parentId populated: 3',
                        '\'legacyId\' column not found. Unable to match parentId to CSV rows.',
                        "'source' option not specified. Unable to check parentId values against AtoM's database.",
                        'Number of rows for which parents could not be found (will import as top level records): 3',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'DJ001,ID1,Some Photographs,,Extent and medium 1,,',
                        'DJ002,,Voûte, étagère 0074,,,,',
                        'DJ003,ID4,Title Four,,,,en',
                    ],
                ],
            ],

            [
                'CsvParentTest-ParentIdColumnEmpty' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_parent_id_column_empty.csv',
                    'testname' => 'CsvParentTest',
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with parentId populated: 0',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvParentTest-ParentIdNoMatches' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvParentTest',
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with parentId populated: 3',
                        "'source' option not specified. Unable to check parentId values against AtoM's database.",
                        'Number of rows for which parents could not be found (will import as top level records): 3',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'B10101,DJ001,ID1,Some Photographs,,Extent and medium 1,,',
                        'D20202,DJ002,,Voûte, étagère 0074,,,,',
                        ',DJ003,ID4,Title Four,,,,en',
                    ],
                ],
            ],

            [
                'CsvParentTest-ParentIdMatchesInFile' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_parent_id_matches.csv',
                    'testname' => 'CsvParentTest',
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with parentId populated: 1',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvParentTest-ParentIdMatchesInFileWithSourceOption' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_parent_id_matches.csv',
                    'testname' => 'CsvParentTest',
                    'validatorOptions' => ['source' => 'testsourcefile.csv'],
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with parentId populated: 1',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvParentTest-ParentIdMatchesInKeymap' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_parent_id_matches_in_keymap.csv',
                    'testname' => 'CsvParentTest',
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with parentId populated: 1',
                        "'source' option not specified. Unable to check parentId values against AtoM's database.",
                        'Number of rows for which parents could not be found (will import as top level records): 1',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'D20202,A10101,,Voûte, étagère 0074,,,,',
                    ],
                ],
            ],

            [
                'CsvParentTest-ParentIdMatchesInKeymapWithSourceOption' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_parent_id_matches_in_keymap.csv',
                    'testname' => 'CsvParentTest',
                    'validatorOptions' => ['source' => 'testsourcefile.csv'],
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with parentId populated: 1',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvParentTest-QubitParentSlug' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_qubit_parent_slug.csv',
                    'testname' => 'CsvParentTest',
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with qubitParentSlug populated: 2',
                        'Number of rows for which parents could not be found (will import as top level records): 1',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'X7,missing-slug,TY99,Some stuff,,,,en',
                    ],
                ],
            ],

            [
                'CsvParentTest-QubitParentIdParentSlugEmpty' => [
                    'csvValidatorClasses' => ['CsvParentTest' => CsvParentTest::class],
                    'filename' => '/unix_csv_parent_id_and_qubit_parent_slug.csv',
                    'testname' => 'CsvParentTest',
                    CsvBaseTest::TEST_TITLE => CsvParentTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvParentTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with parentId populated: 1',
                        'Rows with qubitParentSlug populated: 2',
                        'Rows with both \'parentId\' and \'qubitParentSlug\' populated: 1',
                        'Column \'qubitParentSlug\' will override \'parentId\' if both are populated.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            /*
             * Test CsvLegacyIdTest.class.php
             *
             * Tests:
             * - legacyId col missing
             * - legacyId not populated
             * - legacyId populated
             * - duplicate legacyId
             */
            [
                'CsvLegacyTest-LegacyIdColumnMissing' => [
                    'csvValidatorClasses' => ['CsvLegacyIdTest' => CsvLegacyIdTest::class],
                    'filename' => '/unix_csv_missing_legacy_id.csv',
                    'testname' => 'CsvLegacyIdTest',
                    CsvBaseTest::TEST_TITLE => CsvLegacyIdTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvLegacyIdTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        '\'legacyId\' column not present. Future CSV updates may not match these records.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvLegacyTest-LegacyIdColumnPresent' => [
                    'csvValidatorClasses' => ['CsvLegacyIdTest' => CsvLegacyIdTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvLegacyIdTest',
                    CsvBaseTest::TEST_TITLE => CsvLegacyIdTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvLegacyIdTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        '\'legacyId\' values are all unique.',
                        'Rows with empty \'legacyId\' column: 2',
                        'Future CSV updates may not match these records.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        ',,,Chemise,,,,fr',
                        ',DJ003,ID4,Title Four,,,,en',
                    ],
                ],
            ],

            [
                'CsvLegacyTest-DuplicatedLegacyId' => [
                    'csvValidatorClasses' => ['CsvLegacyIdTest' => CsvLegacyIdTest::class],
                    'filename' => '/unix_csv_with_duplicated_legacy_id.csv',
                    'testname' => 'CsvLegacyIdTest',
                    CsvBaseTest::TEST_TITLE => CsvLegacyIdTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvLegacyIdTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with non-unique \'legacyId\' values: 1',
                        'Rows with empty \'legacyId\' column: 1',
                        'Future CSV updates may not match these records.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        ',,,Chemise,,,,fr',
                        'Non-unique \'legacyId\' values: B10101',
                    ],
                ],
            ],

            /*
             * Test CsvCultureTest.class.php
             *
             * Tests:
             * - culture column missing
             * - culture column present with valid data
             * - culture column present with mix of valid and invalid data
             */
            [
                'CsvCultureTest-CultureColMissing' => [
                    'csvValidatorClasses' => ['CsvCultureTest' => CsvCultureTest::class],
                    'filename' => '/unix_csv_missing_culture.csv',
                    'testname' => 'CsvCultureTest',
                    CsvBaseTest::TEST_TITLE => CsvCultureTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvCultureTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        '\'culture\' column not present in file.',
                        'Rows without a valid culture value will be imported using AtoM\'s default source culture.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvCultureTest-CulturesValid' => [
                    'csvValidatorClasses' => ['CsvCultureTest' => CsvCultureTest::class],
                    'filename' => '/unix_csv_valid_cultures.csv',
                    'testname' => 'CsvCultureTest',
                    CsvBaseTest::TEST_TITLE => CsvCultureTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvCultureTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        '\'culture\' column values are all valid.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvCultureTest-CulturesSomeInvalid' => [
                    'csvValidatorClasses' => ['CsvCultureTest' => CsvCultureTest::class],
                    'filename' => '/unix_csv_cultures_some_invalid.csv',
                    'testname' => 'CsvCultureTest',
                    CsvBaseTest::TEST_TITLE => CsvCultureTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvCultureTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with blank culture value: 1',
                        'Rows with invalid culture values: 1',
                        'Rows with pipe character in culture values: 1',
                        '\'culture\' column does not allow for multiple values separated with a pipe \'|\' character.',
                        'Invalid culture values: fr|en, gg',
                        'Rows with a blank culture value will be imported using AtoM\'s default source culture.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        ',,,Chemise,,,,fr|en',
                        'D20202,DJ002,,Voûte, étagère 0074,,,,gg',
                    ],
                ],
            ],

            /*
             * Test CsvFieldLengthTest.class.php
             *
             * Tests:
             * - no checked columns present
             * - one checked col present, not triggering error
             * - multiple checked cols present, one triggers error
             * - multiple checked cols present, multiple trigger error
             */
            [
                'CsvFieldLengthTest-LengthCheckNonePresent' => [
                    'csvValidatorClasses' => ['CsvFieldLengthTest' => CsvFieldLengthTest::class],
                    'filename' => '/unix_csv_missing_culture.csv',
                    'testname' => 'CsvFieldLengthTest',
                    CsvBaseTest::TEST_TITLE => CsvFieldLengthTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFieldLengthTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'No columns to check.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvFieldLengthTest-LengthCheckValidCulturesPresent' => [
                    'csvValidatorClasses' => ['CsvFieldLengthTest' => CsvFieldLengthTest::class],
                    'filename' => '/unix_csv_cultures_some_invalid.csv',
                    'testname' => 'CsvFieldLengthTest',
                    CsvBaseTest::TEST_TITLE => CsvFieldLengthTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFieldLengthTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Checking columns: culture',
                        '\'culture\' values that exceed 6 characters: 0',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvFieldLengthTest-LengthCheckLanguageCultureError' => [
                    'csvValidatorClasses' => ['CsvFieldLengthTest' => CsvFieldLengthTest::class],
                    'filename' => '/unix_csv_culture_language_length_error.csv',
                    'testname' => 'CsvFieldLengthTest',
                    CsvBaseTest::TEST_TITLE => CsvFieldLengthTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFieldLengthTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        'Checking columns: culture,language',
                        '\'culture\' values that exceed 6 characters: 0',
                        '\'language\' column may have invalid values.',
                        '\'language\' values that exceed 6 characters: 1',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'language column value: english',
                    ],
                ],
            ],

            [
                'CsvFieldLengthTest-LengthCheckLanguageCultureMultErrors' => [
                    'csvValidatorClasses' => ['CsvFieldLengthTest' => CsvFieldLengthTest::class],
                    'filename' => '/unix_csv_culture_language_length_errors.csv',
                    'testname' => 'CsvFieldLengthTest',
                    CsvBaseTest::TEST_TITLE => CsvFieldLengthTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvFieldLengthTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        'Checking columns: culture,language',
                        '\'culture\' column may have invalid values.',
                        '\'culture\' values that exceed 6 characters: 1',
                        '\'language\' column may have invalid values.',
                        '\'language\' values that exceed 6 characters: 2',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'language column value: this is spanish',
                        'culture column value: Germany',
                        'language column value: english',
                    ],
                ],
            ],

            /*
             * Test CsvDuplicateColumnNameTest.class.php
             *
             * Tests:
             * - no duplicated column headers
             * - one duplicated column header
             * - mulitple different duplicated column headers
             */
            [
                'CsvDuplicateColumnNameTest-NoDuplicatedColumnHeader' => [
                    'csvValidatorClasses' => ['CsvDuplicateColumnNameTest' => CsvDuplicateColumnNameTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvDuplicateColumnNameTest',
                    CsvBaseTest::TEST_TITLE => CsvDuplicateColumnNameTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvDuplicateColumnNameTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'No duplicate column names found.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDuplicateColumnNameTest-OneDuplicatedColumnHeader' => [
                    'csvValidatorClasses' => ['CsvDuplicateColumnNameTest' => CsvDuplicateColumnNameTest::class],
                    'filename' => '/unix_csv_one_duplicated_header.csv',
                    'testname' => 'CsvDuplicateColumnNameTest',
                    CsvBaseTest::TEST_TITLE => CsvDuplicateColumnNameTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvDuplicateColumnNameTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Columns with name \'repository\': 2',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDuplicateColumnNameTest-DuplicatedColumnHeaders' => [
                    'csvValidatorClasses' => ['CsvDuplicateColumnNameTest' => CsvDuplicateColumnNameTest::class],
                    'filename' => '/unix_csv_duplicated_headers.csv',
                    'testname' => 'CsvDuplicateColumnNameTest',
                    CsvBaseTest::TEST_TITLE => CsvDuplicateColumnNameTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvDuplicateColumnNameTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Columns with name \'culture\': 3',
                        'Columns with name \'repository\': 2',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            /*
             * Test CsvColumnNameTest.class.php
             *
             * Tests:
             * - class-name not set
             * - all columns validate against config file
             * - some columns fail to validate without matching by lower case
             * - some columns fail to validate but match by lower case
             */
            [
                'CsvColumnNameTest-ClassNameNotSet' => [
                    'csvValidatorClasses' => ['CsvColumnNameTest' => CsvColumnNameTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvColumnNameTest',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                    ],
                    CsvBaseTest::TEST_TITLE => CsvColumnNameTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnNameTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of unrecognized column names found in CSV: 0',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvColumnNameTest-AllColumnNamesMatch' => [
                    'csvValidatorClasses' => ['CsvColumnNameTest' => CsvColumnNameTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvColumnNameTest',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                    ],
                    CsvBaseTest::TEST_TITLE => CsvColumnNameTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnNameTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of unrecognized column names found in CSV: 0',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvColumnNameTest-SomeUnmatched' => [
                    'csvValidatorClasses' => ['CsvColumnNameTest' => CsvColumnNameTest::class],
                    'filename' => '/unix_csv_unknown_column_name.csv',
                    'testname' => 'CsvColumnNameTest',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                    ],
                    CsvBaseTest::TEST_TITLE => CsvColumnNameTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnNameTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of unrecognized column names found in CSV: 1',
                        'Unrecognized columns will be ignored by AtoM when the CSV is imported.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'Unrecognized column: levilOfDescrooption',
                    ],
                ],
            ],

            [
                'CsvColumnNameTest-BadCaseColumnName' => [
                    'csvValidatorClasses' => ['CsvColumnNameTest' => CsvColumnNameTest::class],
                    'filename' => '/unix_csv_bad_case_column_name.csv',
                    'testname' => 'CsvColumnNameTest',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                    ],
                    CsvBaseTest::TEST_TITLE => CsvColumnNameTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvColumnNameTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        'Number of unrecognized column names found in CSV: 2',
                        'Unrecognized columns will be ignored by AtoM when the CSV is imported.',
                        'Number of column names with leading or trailing whitespace characters: 1',
                        'Number of unrecognized columns that may be case related: 1',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'Unrecognized column:  identifier',
                        'Unrecognized column: Title',
                        'Column names with leading or trailing whitespace: identifier',
                        'Possible match for Title: title',
                    ],
                ],
            ],

            /*
             * Test CsvLanguageTest.class.php
             *
             * Tests:
             * - language column missing
             * - language column present with valid data
             * - language column present with mix of valid and invalid data
             */
            [
                'CsvLanguageTest-LanguageColMissing' => [
                    'csvValidatorClasses' => ['CsvLanguageTest' => CsvLanguageTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvLanguageTest',
                    CsvBaseTest::TEST_TITLE => CsvLanguageTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvLanguageTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        '\'language\' column not present in file.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvLanguageTest-LanguageValid' => [
                    'csvValidatorClasses' => ['CsvLanguageTest' => CsvLanguageTest::class],
                    'filename' => '/unix_csv_valid_languages.csv',
                    'testname' => 'CsvLanguageTest',
                    CsvBaseTest::TEST_TITLE => CsvLanguageTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvLanguageTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        '\'language\' column values are all valid.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvLanguageTest-LanguagesSomeInvalid' => [
                    'csvValidatorClasses' => ['CsvLanguageTest' => CsvLanguageTest::class],
                    'filename' => '/unix_csv_languages_some_invalid.csv',
                    'testname' => 'CsvLanguageTest',
                    CsvBaseTest::TEST_TITLE => CsvLanguageTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvLanguageTest::RESULT_ERROR,
                    CsvBaseTest::TEST_RESULTS => [
                        'Rows with invalid language values: 2',
                        'Rows with pipe character in language values: 1',
                        '\'language\' column does not allow for multiple values separated with a pipe \'|\' character.',
                        'Invalid language values: Spanish, fr|en, en_gb',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        'B10101,DJ001,ID1,Some Photographs,,Extent and medium 1,,es,Spanish',
                        ',,,Chemise,,,,fr,fr|en',
                        ',DJ003,ID4,Title Four,,,,en,en_gb',
                    ],
                ],
            ],
            /*
             * Test CsvEventValuesTest.class.php
             *
             * Tests:
             * - event columns missing
             * - subset of event columns present w. each field populated with same # of values
             * - subset of event columns present w. each field populated with different # of values
             * - all event columns present w. each field populated with same # (> 1) of values
             */
            [
                'CsvEventValuesTest-EventColsMissing' => [
                    'csvValidatorClasses' => ['CsvEventValuesTest' => CsvEventValuesTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvEventValuesTest',
                    CsvBaseTest::TEST_TITLE => CsvEventValuesTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvEventValuesTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'No event columns to check.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvEventValuesTest-WithEventTypeAndDates' => [
                    'csvValidatorClasses' => ['CsvEventValuesTest' => CsvEventValuesTest::class],
                    'filename' => '/unix_csv_with_event_type.csv',
                    'testname' => 'CsvEventValuesTest',
                    CsvBaseTest::TEST_TITLE => CsvEventValuesTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvEventValuesTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Checking columns: eventTypes,eventDates,eventStartDates,eventEndDates',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvEventValuesTest-WithEventTypeAndDateMismatches' => [
                    'csvValidatorClasses' => ['CsvEventValuesTest' => CsvEventValuesTest::class],
                    'filename' => '/unix_csv_with_event_type_mismatches.csv',
                    'testname' => 'CsvEventValuesTest',
                    CsvBaseTest::TEST_TITLE => CsvEventValuesTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvEventValuesTest::RESULT_WARN,
                    CsvBaseTest::TEST_RESULTS => [
                        'Checking columns: eventTypes,eventDates,eventStartDates,eventEndDates',
                        'Event value mismatches found: 1',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                        ',,,Chemise,,,creation|donation,2010,01-01-2010,,,fr',
                    ],
                ],
            ],

            [
                'CsvEventValuesTest-WithEventTypeAllColsMatching' => [
                    'csvValidatorClasses' => ['CsvEventValuesTest' => CsvEventValuesTest::class],
                    'filename' => '/unix_csv_with_event_type_all_cols.csv',
                    'testname' => 'CsvEventValuesTest',
                    CsvBaseTest::TEST_TITLE => CsvEventValuesTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvEventValuesTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        'Checking columns: eventTypes,eventDates,eventStartDates,eventEndDates,eventActors,eventActorHistories,eventPlaces',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            /*
             * Test CsvDigitalObjectPathTest.class.php
             *
             * Add test for new param.
             *
             * Tests:
             * - digitalObjectPath column missing
             * - digitalObjectPath column present but empty
             * - digitalObjectPath column present and populated with:
             * -- valid file path
             * -- duplicated file path
             * -- invalid file path
             * -- empty value
             * -- digitalObjectUri column present and populated
             */
            [
                'CsvDigitalObjectPathTest-digitalObjectPathMissing' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectPathTest' => CsvDigitalObjectPathTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvDigitalObjectPathTest',
                    CsvBaseTest::TEST_TITLE => CsvDigitalObjectPathTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvDigitalObjectPathTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        "Column 'digitalObjectPath' not present in CSV. Nothing to verify.",
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDigitalObjectPathTest-digitalObjectPathEmpty' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectPathTest' => CsvDigitalObjectPathTest::class],
                    'filename' => '/unix_csv_with_digital_object_cols.csv',
                    'testname' => 'CsvDigitalObjectPathTest',
                    CsvBaseTest::TEST_TITLE => CsvDigitalObjectPathTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvDigitalObjectPathTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        "Column 'digitalObjectPath' found.",
                        'Digital object folder location not specified.',
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDigitalObjectPathTest-digitalObjectPathEmpty' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectPathTest' => CsvDigitalObjectPathTest::class],
                    'filename' => '/unix_csv_with_digital_object_cols.csv',
                    'testname' => 'CsvDigitalObjectPathTest',
                    'validatorOptions' => [
                        'source' => 'testsourcefile.csv',
                        'className' => 'QubitInformationObject',
                        'className' => 'QubitInformationObject',
                        'pathToDigitalObjects' => '/digital_objects',
                    ],
                    CsvBaseTest::TEST_TITLE => CsvDigitalObjectPathTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvDigitalObjectPathTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        "Column 'digitalObjectPath' found.",
                        "Column 'digitalObjectPath' is empty.",
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            /*
             * Test CsvDigitalObjectUriTest.class.php
             *
             * Tests:
             * - digitalObjectUri column missing
             * - digitalObjectUri column present but empty
             * - digitalObjectUri column present and populated with:
             * -- valid URI
             * -- incorrect scheme URI (e.g. ftp://)
             * -- duplicated URI
             * -- invalid URI
             * -- empty value
             * -- digitalObjectUri column present and populated
             */
            [
                'CsvDigitalObjectUriTest-digitalObjectUriMissing' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectUriTest' => CsvDigitalObjectUriTest::class],
                    'filename' => '/unix_csv_without_utf8_bom.csv',
                    'testname' => 'CsvDigitalObjectUriTest',
                    CsvBaseTest::TEST_TITLE => CsvDigitalObjectUriTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvDigitalObjectUriTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        "Column 'digitalObjectUri' not present in CSV. Nothing to verify.",
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],

            [
                'CsvDigitalObjectUriTest-digitalObjectUriEmpty' => [
                    'csvValidatorClasses' => ['CsvDigitalObjectUriTest' => CsvDigitalObjectUriTest::class],
                    'filename' => '/unix_csv_with_digital_object_cols.csv',
                    'testname' => 'CsvDigitalObjectUriTest',
                    CsvBaseTest::TEST_TITLE => CsvDigitalObjectUriTest::TITLE,
                    CsvBaseTest::TEST_STATUS => CsvDigitalObjectUriTest::RESULT_INFO,
                    CsvBaseTest::TEST_RESULTS => [
                        "Column 'digitalObjectUri' found.",
                        "Column 'digitalObjectUri' is empty.",
                    ],
                    CsvBaseTest::TEST_DETAIL => [
                    ],
                ],
            ],
        ];
    }

    // Generic Validation
    protected function runValidator($csvValidator, $filenames, $tests, $verbose = true)
    {
        $csvValidator->setCsvTests($tests);
        $csvValidator->setFilenames(explode(',', $filenames));
        $csvValidator->setVerbose($verbose);
        $csvValidator->setOrmClasses($this->ormClasses);

        return $csvValidator->validate();
    }
}
