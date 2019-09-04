<?
    require 'vendor/autoload.php';

    use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

    class PHPExcel{
        
        /**
         * Поле для пути к обрабатываемому файду
         * @var string
         */
        private $filepath;



        /**
         * Конструктор класса
         */
        function __construct( $filepath = NULL ){
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);

            $this->filepath = $filepath;
        }

        /**
         * Метод превращения файла в многомерный массив
         * Возвращает массив строк
         * 
         * @return array[]
         */
        public function XLSXReader(){
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            
            $spreadsheet = $reader->load( $this->filepath );
            
            unlink( $this->filepath );
            return $spreadsheet->getActiveSheet()->toArray();
        }

        /**
         * Метод записи в файл
         * @param string[]
         * 
         * @return string
         */
        public function XLSXWriter( $name, $data ){
            
            if( empty($name) ){
                die( "<p><i color='red'>Имя файла не задано!</i></p>" );
            }else{
                $spreadsheet = new Spreadsheet();
                    
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setCellValue('A1', 'ID');
                $sheet->setCellValue('B1', 'Название');
                $sheet->setCellValue('C1', 'Адрес');
                $sheet->setCellValue('D1', 'Соцсети');
                $sheet->setCellValue('E1', 'Телефоны');

                foreach( $data as $key => $value ){
                    $sheet->setCellValue('A'.($key + 2), $value['id']);
                    $sheet->setCellValue('B'.($key + 2), $value['company']);
                    $sheet->setCellValue('C'.($key + 2), $value['address']);
                    $sheet->setCellValue('D'.($key + 2), implode("\n", $value['socialNetworks']));
                    $sheet->setCellValue('E'.($key + 2), implode("\n", $value['telephones']));
                }

                foreach($sheet->getRowDimensions() as $rowID) { 
                    $rd->setRowHeight(-1); 
                }
                
                $writer = new Xlsx( $spreadsheet );
                
                $filepath = $_SERVER['DOCUMENT_ROOT'] . '/XLSX/'.$name.'-'.date('d-m-Y_H-i-s', time()).'.xlsx';
                
                $writer->save( $filepath );
                
                if( file_exists($filepath) ){
                    echo $filepath;
                    exit;
                }else{
                    die("<p color='red'><i>Ошибка при создании файла!</i></p>");
                }
            }
        }

        /**
         * Метод преобразования массива с данными в структурированный объект с вариантами товара
         * Возвращает JSON с данными
         * @param int - Количество ненужных строк
         * 
         * @return string
         */
        public function XLSXToVariantsArray( int $trash_fields_count = 0 ){

            /**
             * @var array[] Будущий массив с объектами
             * @var array[] Пустой массив для очистки входящего от пустых значений
             */
            $objects =  $temp_array = [];

            /**
             * @var bool Флаг описания объекта
             */
            $description_flag = true;
            
            /**
             * @var string Описание
             * @var string Имя объекта
             */
            $description = $object_name = NULL;
            
            /**
             * @var int Индекс варианта
             * @var int Индекс бесполезных для объекта полей
             */
            $variant_index = $trash_field_index = 0;

            $nc_core = nc_core::get_object();

            #Вызываем метод получения массива из файла
            $array = $this->XLSXReader( $this->filepath );

            #Избавим этот прекрасный массив от пустых значений
            foreach( $array as $key => $value ){
                $temp = array_diff( $value, [''] );

                if( count($temp) > 0) array_push($temp_array, $temp);
            }

            foreach( $temp_array as $key => $value ){
                #Если флаг описания то задаём описание
                if( $description_flag == true ){

                    #Задаём описание в переменную
                    $description = $value[0];
                    #Выключаем флаг описания
                    $description_flag = false;

                }else{

                    #Если количество элементов в массиве и не флаг
                    if( count( $value ) == 1 ){
                        #Задаём имя объекта
                        $object_name = $value[0];
                        #И описание
                        $objects[$object_name]['description'] = $description;
                        $variant_index = $trash_field_index = 0;
                    }else{

                        #Проверка на ненужные строки после имени
                        if( $trash_field_index == $trash_fields_count ){
                            #Наполняем объект
                            foreach( $value as $item_key => $data ){
                                //Меняем запятые на точки в числах типа float
                                $formated_data = str_replace( ',', '.', $data );

                                $objects[$object_name]['items'][$variant_index][$item_key] = is_numeric($formated_data) ? (float)$formated_data : $formated_data ;
                            }
                            $variant_index++;

                        }else{
                            $trash_field_index++;
                        }

                        #Проверка на наличие нового объекта
                        if( isset($temp_array[$key+1]) ){
                            if( count($temp_array[$key+1])  == 1 ){
                                #Проверка на наличие нового описания
                                if( isset($temp_array[$key+2]) ){
                                    if( count($temp_array[$key+2]) == 1 ){
                                        $description_flag = true;
                                    }
                                }
                            }
                        }

                    }

                }
            }
            
            return nc_array_json($objects);
        }

        /**
         * Метод добавления объектов в базу
         * Возвращает количество добавленных объектов
         * Принимает:
         * @param int - количество ненужных строк
         * @param string - добавляемые поля
         * @param int - ID компонента
         * @param int - ID раздела
         * @param int - ID инфоблока
         */
        public function InsertObjects( int $trash_fields_count = 1, string $fields, int $insert_component, int $insert_sub, int $insert_sub_class ){

            /**
             * Получаем объект из XLSX файла
             * @var object[]
             */
            $objects = json_decode( $this->XLSXToVariantsArray( $trash_fields_count ) );

            /**
             * Объявляем переменную nc_core из Netcat
             * @var object[]
             */
            $nc_core = nc_core::get_object();

            if( $fields && $insert_sub && $insert_sub_class && $insert_component ){

                #Перебор товаров
                foreach( $objects as $key => $value ){
                    #Добавление товаров
                    $nc_core->db->query("INSERT INTO `Message".$insert_component."` ( `Subdivision_ID`, `Sub_Class_ID`, `Checked`, `Parent_Message_ID`, `Name`, `VariantName`, `Type` ) VALUES ( ".$insert_sub.", ".$insert_sub_class.", 1, 0, '".$key."', '".$key."', '".$value->description."' )");
                    $parent_id = $nc_core->db->insert_id;

                    #Перебор вариантов товаров
                    foreach( $value->items as $item_key => $item_value ){
                        
                        /**
                         * Массив типизированных данных
                         * @var array[]
                         */
                        $item_values_typed = [];
                        foreach ($item_value as $item) {
                            if( is_numeric($item) ){
                                array_push( $item_values_typed, str_replace(',', '.', $item) );
                            }else{
                                array_push( $item_values_typed, "'".$item."'" );
                            }
                        }
                        $item_values_typed = implode(', ', $item_values_typed);

                        #Добавляем типизированные варианты товаров
                        $nc_core->db->query("INSERT INTO `Message".$insert_component."` ( `Subdivision_ID`, `Sub_Class_ID`, `Checked`, `Parent_Message_ID`, `Name`, `Type`, ".$fields." ) VALUES ( ".$insert_sub.", ".$insert_sub_class.", 1, ".$parent_id.", '".$key."', '".$value->description."', ".$item_values_typed." )");
                    }
                }

                echo $nc_core->db->num_queries;

            }else{
                die("<p style='color: red;'><i>Вы не задали поля для наполнения</i></p>");
            }
        }

        
        /**
         * Метод возвращает данные из столбца в json формате
         * Принимает @param string - наименование столбца
         * 
         * @return string
         */
        public function getColumnByName( String $columnName ){

            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();

            /**
             * @var object[] Загруженый файл в формате xlsx
             * @var array[] Массив листов в файле
             */
            $spreadsheet = $reader->load( $this->filepath );
            $worksheetNames = $spreadsheet->getSheetNames();
            
            /**
             * @var int[] Индекс искомой колонки
             * @var array[] Результативный массив
             */
            $columnIndex;
            $result = [];
            
            foreach( $worksheetNames as $worksheetIndex => $worksheetName ){
                
                /**
                 * @var object[] Текущий рабочий лист
                 * @var int Количество строк в текущей таблице
                 */
                $worksheet = $spreadsheet->getSheetByName( $worksheetName );
                $count = count( $worksheet->toArray() );

                foreach( $worksheet->getRowIterator() as $rowIndex => $row ){
                    foreach( $row->getCellIterator() as $key => $cell ){
                        if( $cell->getCalculatedValue() == $columnName ){
                            $columnLetter = $cell->getColumn();
                            $columnIndex = Coordinate::columnIndexFromString( $columnLetter );

                            for( $i = $rowIndex+1; $i < $count; $i++ ){
                                $result[$worksheetName][] .= trim( $worksheet->getCellByColumnAndRow( $columnIndex, $i ) ) ?: NULL;
                            }

                            break;
                        }
                    }
                }

            }

            unlink( $this->filepath );

            return nc_array_json( $result );
        }

        /**
         * Метод добавления объектов с разделами и подразделами
         * Принимает @param int ID раздела
         */
        public function InsertObjectsWithSubs( int $parent_sub ){
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            
            /**
             * @var object[] Объект класса nc_Core
             * @var object[] Загруженый файл в формате xlsx
             * @var array[] Массив листов в файле
             * @var array[] Массив подразделов
             * @var array[] Массив зависимостей полей
             */
            $nc_core = nc_Core::get_object();
            $spreadsheet = $reader->load( $this->filepath );
            $worksheetNames = $spreadsheet->getSheetNames();
            $subs = $nc_core->db->get_col("SELECT `Subdivision_Name` FROM `Subdivision` WHERE `Parent_Sub_ID` = ".$parent_sub);
            $field_dependency = array(
                'Name' => array(
                    'Name' => 'Модель автомобиля',
                    'Type' => 'string'
                ),
                'Property_Euro' => array(
                    'Name' => 'Экологический класс',
                    'Type' => 'int'
                ),
                'Property_WheelArrangement' => array(
                    'Name' => 'Колёсная формула',
                    'Classificator' => 'WheelArrangement',
                    'Type' => 'list'
                ),
                'Property_CabinType' => array(
                    'Name' => 'Тип кабины',
                    'Type' => 'int'
                ),
                'Property_CabinDescription' => array(
                    'Name' => 'Описание кабины',
                    'Type' => 'string'
                ),
                'Property_Base' => array(
                    'Name' => 'База, мм',
                    'Type' => 'int'
                ),
                'Property_MountingSize' => array(
                    'Name' => 'Монтажный размер, мм',
                    'Type' => 'int'
                ),
                'Property_RearOverhang' => array(
                    'Name' => 'Задний свес, мм',
                    'Type' => 'int'
                ),
                'Property_TowingDevice' => array(
                    'Name' => 'Буксирное устройство',
                    'Classificator' => 'TowingDevice',
                    'Type' => 'list'
                ),
                'Property_Engine' => array(
                    'Name' => 'Двигатель',
                    'Classificator' => 'Engine',
                    'Type' => 'list'
                ),
                'Property_Power' => array(
                    'Name' => 'Мощность, л.с.',
                    'Type' => 'int'
                ),
                'Property_Transmission' => array(
                    'Name' => 'КП',
                    'Classificator' => 'Transmission',
                    'Type' => 'list'
                ),
                'Property_InstallationOfPowerTakeOff' => array(
                    'Name' => 'Установка КОМ (НШ)',
                    'Type' => 'string'
                ),
                'Property_PermissibleTotalWeight' => array(
                    'Name' => 'Допустимая общая масса, кг',
                    'Type' => 'int'
                ),
                'Property_SuperstructureWeight' => array(
                    'Name' => 'Масса надстройки, кг',
                    'Type' => 'int'
                ),
                'Property_RearSuspension' => array(
                    'Name' => 'Задняя подвеска',
                    'Classificator' => 'RearSuspension',
                    'Type' => 'list'
                ),
                'Property_Tires' => array(
                    'Name' => 'Шины',
                    'Classificator' => 'Tires',
                    'Type' => 'list'
                ),
                'Property_Status' => array(
                    'Name' => 'статус',
                    'Type' => 'string'
                ),
                'Note' => array(
                    'Name' => 'Примечания',
                    'Type' => 'string'
                ),
                'Property_PlatformType' => array(
                    'Name' => 'Тип платформы',
                    'Classificator' => 'PlatformType',
                    'Type' => 'list'
                ),
                'Property_PlatformInnerSize' => array(
                    'Name' => 'Внутренние размеры платформы',
                    'Type' => 'string'
                ),
                'Property_GP' => array(
                    'Name' => 'Г/п, кг',
                    'Type' => 'int'
                ),
                'Property_GrossArticulatedLorryWeight' => array(
                    'Name' => 'Полная масса автопоезда, кг',
                    'Type' => 'int'
                ),
                'Property_SaddleHeight' => array(
                    'Name' => 'Bысота по ССУ, мм',
                    'Type' => 'int'
                ),
                'Property_SaddleLoad' => array(
                    'Name' => 'Нагрузка на ССУ, кг',
                    'Type' => 'int'
                ),
                'Property_GrossVehicleWeight' => array(
                    'Name' => 'Полная масса автомобиля, кг',
                    'Type' => 'int'
                ),
                'Property_ArticulatedLorryWeight' => array(
                    'Name' => 'Масса автопоезда, кг',
                    'Type' => 'int'
                ),
                'Property_PlatformVolume' => array(
                    'Name' => 'Объём платформы, м3',
                    'Type' => 'string'
                ),
                'Property_UnloadingType' => array(
                    'Name' => 'вид разгрузки',
                    'Classificator' => 'UnloadingType',
                    'Type' => 'list'
                ),
                'Property_LodgementsCount' => array(
                    'Name' => 'Число ложементов',
                    'Type' => 'string'
                ),
                'Property_PlatformLength' => array(
                    'Name' => 'Длина платформы, мм',
                    'Type' => 'int'
                )
            );

           /*
            $field_dependency = array(
                'Экологичский класс' => 'Property_Euro',
                'Колёсная формула' => 'Property_WheelArrangement',
                'Тип кабины' => 'Property_CabinType',
                'Описание кабины' => 'Property_CabinDescription',
                'База, мм' => 'Property_Base',
                'Монтажный размер, мм' => 'Property_MountingSize',
                'Задний свес, мм' => 'Property_RearOverhang',
                'Буксирное устройство' => 'Property_TowingDevice',
                'Двигатель' => 'Property_Engine',
                'Мощность, л.с.' => 'Property_Power',
                'КП' => 'Property_Transmission',
                '"Установка КОМ (НШ)"' => 'Property_InstallationOfPowerTakeOff',
                'Допустимая общая масса, кг' => 'Property_PermissibleTotalWeight',
                'Масса надстройки, кг' => 'Property_SuperstructureWeight',
                'Задняя подвеска' => 'Property_RearSuspension',
                'Шины' => 'Property_Tires',
                'статус' => 'Property_Status',
                'Примечания' => 'Note',
                'Тип платформы' => 'Property_PlatformType',
                'Внутренние размеры платформы' => 'Property_PlatformInnerSize',
                'Г/п, кг' => 'Property_GP',
                'Полная масса автопоезда, кг' => 'Property_GrossArticulatedLorryWeight',
                'Bысота по ССУ, мм' => 'Property_SaddleHeight',
                'Нагрузка на ССУ, кг' => 'Property_SaddleLoad',
                'Полная масса автомобиля, кг' => 'Property_GrossVehicleWeight',
                'Масса автопоезда, кг' => 'Property_ArticulatedLorryWeight',
                'Объём платформы, м3' => 'Property_PlatformVolume',
                'вид разгрузки' => 'Property_UnloadingType',
                'Число ложементов' => 'Property_LodgementsCount'
            );
            */

            /**
             * @var int Компонент каталога
             * @var int Шаблон категорий
             * @var int Компонент товара
             * @var int Шаблон для фильтров
             */
            $catalogue_component = 112;
            $_category_template = 113;
            $product_component = 111;
            $_product_template = 115;



            foreach( $worksheetNames as $worksheetName ){
                
                /**
                 * @var object[] Текущий рабочий лист
                 * @var string Последняя колонка на листе
                 * @var boolean Строка с заголовком
                 * @var array[] Наименование товара
                 * @var int ID раздела
                 * @var string Транслитерированное наименование для раздела
                 * @var string ЧПУ
                 * @var int Приоритет раздела с подкатегориями
                 * @var int Приоритет раздела с моделями
                 */
                $worksheet = $spreadsheet->getSheetByName( $worksheetName );
                $last_col = $worksheet->getHighestColumn();
                $begRow = false;
                $product_name = [];
                $subdivision_id = $english_name = $hidden_url = NULL;
                $category_priority = $model_priority = 0;

                /**
                 * Проверка на наличие раздела
                 * если нет, то создать новый
                 */
                if( !in_array($worksheetName, $subs) ){
                    
                    $english_name = str2url($subcategory_name);
                    $hidden_url = $nc_core->db->get_var("SELECT `Hidden_URL` FROM `Subdivision` WHERE `Subdivision_ID` = ".$parent_sub).str2url($worksheetName)."/";
                    
                    $nc_core->db->query("INSERT INTO `Subdivision` 
                                            ( `Catalogue_ID`, `Parent_Sub_ID`, `Subdivision_Name`, `EnglishName`, `Hidden_URL`, `Checked`, `UseMultiSubClass`, `DisplayType`, `Created`, `LastModified` ) VALUES 
                                            ( 1, $parent_sub, '".$worksheetName."', '".$english_name."', '".$hidden_url."', 1, 1, 'inherit', NOW(), NOW() ) ");
                    
                    $subdivision_id = $nc_core->db->insert_id;
                    if ( $subdivision_id ) $total_categories++;
                }else{
                    $subdivision_id = $nc_core->db->get_var("SELECT `Subdivision_ID` FROM `Subdivision` WHERE `Subdivision_Name` = '".$worksheetName."'");
                }

                /**
                 * Проверка на наличие инфоблока
                 * @var int - ID инфоблока
                 */
                $sub_class = $nc_core->db->get_var("SELECT `Sub_Class_ID` FROM `Sub_Class` WHERE `Subdivision_ID` = ".$subdivision_id." AND `Class_ID` = ".$catalogue_component);
                #Если нет инфоблока - добавляем
                if( empty( $sub_class ) ) $sub_class = $this->InsertSubClass( $subdivision_id, $catalogue_component, $_category_template, 'Каталог', 'katalog' );
                
                #Бегаем по строкам и столбцам пока не походим до заголовков полей
                foreach( $worksheet->getRowIterator() as $rowIndex => $row ){
                    /**
                     * @var array[] Массив значений в текущей строке
                     */
                    $row_to_array = $worksheet->rangeToArray('B'.$rowIndex.':'.$last_col.$rowIndex)[0];

                    foreach( $row->getCellIterator() as $cell ){
                        /**
                         * Если заголовки не найдены, то ищем
                         * Иначе парсим каталог. 
                         */
                        if( $begRow === false ){
                            #Если наткнулись на заголовок, то больше сюда ен заходим и прыгаем на следующую строку
                            if( $cell->getValue() == "№ п/п" ){

                                /**
                                 * @var array[] Масств поелй из базы
                                 * @var int Приоритет для 
                                 */
                                $sql_fields = [];
                                $item_priority = 0;

                                foreach( $row_to_array as $value ){
                                    foreach( $field_dependency as $name => $data ){
                                        if( preg_replace ('/\s/', ' ', $value) == $data['Name'] ){
                                           array_push( $sql_fields, preg_replace ('/\s/', ' ', $name) );
                                        }
                                    }
                                }

                                $begRow = true;
                                break;
                            }
                        }else{
                            #Если наткнулись на заголовок товара и он не пустая ячейка
                            if( $cell->getColumn() == 'B' && !empty( $cell->getValue() ) ){
                                $product_name = explode('-', $cell->getValue() );

                                /**
                                 * @var string Наименование подраздела получаем из первого слова в ячейеке + 4 символа из 2 слова
                                 * @var string Транслитерированное наименование для раздела
                                 * @var string ЧПУ подраздела
                                 * @var int ID подраздела
                                 */
                                $subcategory_name = $product_name[0]." ".mb_substr( $product_name[1], 0, 4 );
                                $english_name = str2url($subcategory_name);
                                $hidden_url = $nc_core->db->get_var("SELECT `Hidden_URL` FROM `Subdivision` WHERE `Subdivision_ID` = ".$subdivision_id).$english_name."/";
                                $subcategory_id = $nc_core->db->get_var("SELECT `Subdivision_ID` FROM `Subdivision` WHERE `Parent_Sub_ID` = ".$subdivision_id." AND `Subdivision_Name` = '".$subcategory_name."'");

                                /**
                                 * Добавляем уровень подкатегорий
                                 */

                                #Если подкатегорий нет, то создаём новый в текущий раздел
                                if( empty($subcategory_id) ){
                                    $model_priority = 0;
                                    $subcategory_id = $this->InsertSubdivision( $subdivision_id, $category_priority++, $subcategory_name, $english_name, $hidden_url );

                                    if( $subcategory_id ) $total_subcategories++;
                                }

                                /**
                                 * @var int ID Инфоблока для подкатегории
                                 */
                                $subcategory_sub_class = $nc_core->db->get_var("SELECT `Sub_Class_ID` FROM `Sub_Class` WHERE `Subdivision_ID` = ".$subcategory_id." AND `Class_ID` = ".$product_component);
                                
                                #Если нет инфоблока - добавляем
                                if( empty( $subcategory_sub_class ) ) $subcategory_sub_class = $this->InsertSubClass( $subcategory_id, $product_component, $_product_template, 'Продукция', 'product' );

                                /**
                                 * Добавляем уровень моделей
                                 * 
                                 * @var string Наименование раздела модели товара
                                 * @var string Транслитерированное наименование для раздела
                                 * @var string ЧПУ модели
                                 * @var int ID раздела модели
                                 */
                                $model_name = $product_name[0]." ".$product_name[1];
                                $english_name = str2url($model_name);
                                $hidden_url = $nc_core->db->get_var("SELECT `Hidden_URL` FROM `Subdivision` WHERE `Subdivision_ID` = ".$subcategory_id).$english_name."/";
                                $model_id = $nc_core->db->get_var("SELECT `Subdivision_ID` FROM `Subdivision` WHERE `Parent_Sub_ID` = ".$subcategory_id." AND `Subdivision_Name` = '".$model_name."'");

                                if( empty($model_id) ){
                                    $model_id = $this->InsertSubdivision( $subcategory_id, $model_priority++, $model_name, $english_name, $hidden_url );
                                    if( $model_id ) $total_models++;
                                }
                                
                                /**
                                 * @var int ID Инфоблока для моделей
                                 */
                                $model_sub_class = $nc_core->db->get_var("SELECT `Sub_Class_ID` FROM `Sub_Class` WHERE `Subdivision_ID` = ".$model_id." AND `Class_ID` = ".$product_component);
                                
                                #Если нет инфоблока - добавляем
                                if( empty( $model_sub_class ) ) $model_sub_class = $this->InsertSubClass( $model_id, $product_component, $product_component, 'Продукция', 'product' );

                                /**
                                 * Добавляем объекты в базу
                                 * @var array[] Массив строк для добавления в базу
                                 * @var array[] Массив значений для добавления в базу
                                 */
                                $sql_fields_screened = $sql_fields_values = [];

                                foreach( $sql_fields as $key => $value ){
                                    /**
                                     * @var string Тип данных
                                     * @var string Наименование списка
                                     */
                                    $field_type = $field_dependency[$value]['Type'];
                                    if ( isset( $field_dependency[$value]['Classificator'] ) ){
                                        $field_classificator = $field_dependency[$value]['Classificator'];
                                    }
                                    $field_value = trim(preg_replace( '/\s/', ' ', $row_to_array[$key] ));

                                    array_push( $sql_fields_screened, '`'.$value.'`' );

                                    switch( $field_type ){
                                        case 'int':
                                            array_push( $sql_fields_values, (int)$field_value );
                                        break;
                                        case 'string':
                                            array_push( $sql_fields_values, "'".$field_value."'" );
                                        break;
                                        case 'list':
                                            $insert_value = $nc_core->db->get_var( "SELECT `".$field_classificator."_ID` FROM `Classificator_".$field_classificator."` WHERE `".$field_classificator."_Name` = '".$field_value."'" );
                                            array_push( $sql_fields_values, (int)$insert_value );
                                            // echo "SELECT `".$field_classificator."_ID` FROM `Classificator_".$field_classificator."` WHERE `".$field_classificator."_Name` = '".$field_value."'"."<br>";
                                        break;
                                    }
                                    
                                }
                                // echo implode( ', ', $sql_fields_values )."<br><br>";
                                #Добавляем объекты в базу
                                $nc_core->db->query("INSERT INTO `Message111` 
                                    ( `User_ID`, `Subdivision_ID`, `Sub_Class_ID`, `Priority`, `Checked`, `Parent_Message_ID`, `Created`, `InStock`, ".implode( ', ', $sql_fields_screened )." ) 
                                    VALUES 
                                    ( 1, ".$model_id.", ".$model_sub_class.", ".$item_priority++.", 1, 0, NOW(), 1, ".implode( ', ', $sql_fields_values )." )");
                                if( $nc_core->db->insert_id ) $total_items++;
                                break;

                            }
                        }
                    }
                }
            }
            
            $return = "";
            if( isset($total_categories) ) $return .="Добавлено ".$total_categories." категорий<br>";
            if( isset($total_subcategories) ) $return .="Добавлено ".$total_subcategories." подкатегорий<br>";
            if( isset($total_models) ) $return .="Добавлено ".$total_models." моделей товаров<br>";
            if( isset($total_items) ) $return .="Добавлено ".$total_items." товаров<br>";

            echo $return;
        }

        /**
         * Метод добавляет инфоблок в раздел и возвращает его ID
         * 
         * @param int ID раздела
         * @param int ID компонента
         * @param int ID шаблона компонента
         * @param string Наименование раздела
         * @param string Транслитерированное наименование раздела
         * 
         * @return int
         */
        private function InsertSubClass(int $sub, int $classID, int $templateID, string $sub_class_name, string $sub_class_english_name){
            $nc_core = nc_Core::get_object();
            $nc_core->db->query("INSERT INTO `Sub_Class` VALUES (NULL, ".$sub.", ".$classID.", '".$sub_class_name."', 0, 0, 0, '".$sub_class_english_name."', 1, 1, 0, 0, 0, 0, 0, NULL, -1, NULL, NULL, NULL, 'Priority', NOW(), NOW(), 'index', -1, -1, NULL, ".$templateID.", 0, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0 ) ");
            
            return $nc_core->db->insert_id;
        }

        /**
         * Метод добавления разделов в базу, возвращает ID добавленного раздела
         * @param int ID родительского раздела
         * @param int Приоритет
         * @param string Наименование раздела
         * @param string Транслитерированное наименование раздела
         * @param string ЧПУ
         * 
         * @return int
         */
        private function InsertSubdivision( int $parent_sub, int $priority, string $subdivision_name, string $english_name, string $hidden_url ){
            $nc_core = nc_Core::get_object();
            $nc_core->db->query("INSERT INTO `Subdivision` ( `Catalogue_ID`, `Parent_Sub_ID`, `Subdivision_Name`, `EnglishName`, `Hidden_URL`, `Checked`, `UseMultiSubClass`, `DisplayType`, `Created`, `LastModified`, `Priority` ) VALUES ( 1, $parent_sub, '".$subdivision_name."', '".$english_name."', '".$hidden_url."', 1, 1, 'inherit', NOW(), NOW(), ".$priority." )");
            
            return $nc_core->db->insert_id;
        }
    }

    /**
     * Функция транслитеризации строки, возвращает строку
     * @param string Обрабатываемая строка
     * 
     * @return string
     */
    function rus2translit($string) {
        $converter = array(
            'а' => 'a',   'б' => 'b',   'в' => 'v',
            'г' => 'g',   'д' => 'd',   'е' => 'e',
            'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
            'и' => 'i',   'й' => 'y',   'к' => 'k',
            'л' => 'l',   'м' => 'm',   'н' => 'n',
            'о' => 'o',   'п' => 'p',   'р' => 'r',
            'с' => 's',   'т' => 't',   'у' => 'u',
            'ф' => 'f',   'х' => 'h',   'ц' => 'c',
            'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
            'ь' => '\'',  'ы' => 'y',   'ъ' => '\'',
            'э' => 'e',   'ю' => 'yu',  'я' => 'ya',
            
            'А' => 'A',   'Б' => 'B',   'В' => 'V',
            'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
            'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
            'И' => 'I',   'Й' => 'Y',   'К' => 'K',
            'Л' => 'L',   'М' => 'M',   'Н' => 'N',
            'О' => 'O',   'П' => 'P',   'Р' => 'R',
            'С' => 'S',   'Т' => 'T',   'У' => 'U',
            'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
            'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
            'Ь' => '\'',  'Ы' => 'Y',   'Ъ' => '\'',
            'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
        );
        return strtr($string, $converter);
    }
    
    /**
     * Функция обработки строки для ЧПУ, возвращает строку
     * @param string Исходная строка
     * 
     * @return string
     */
    function str2url($str) {
        // переводим в транслит
        $str = rus2translit($str);
        // в нижний регистр
        $str = strtolower($str);
        // заменям все ненужное нам на "-"
        $str = preg_replace('~[^-a-z0-9_]+~u', '-', $str);
        // удаляем начальные и конечные '-'
        $str = trim($str, "-");
        return $str;
    }