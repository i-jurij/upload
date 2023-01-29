<?php
/**
 * Only for single or multiple file uploads in next format: 
 * A) <input type="file" name="name" > or 
 * B) <input type="file" name="names[]" >
 * 
 * $this __construct normalize $_ FILES_array:
 * $this->files = ['input_name_for_single_upload' => 
 *                      [ 0 => ['name', 'full_path', 'type', 'tmp_name', 'error', 'size'] ],
 *                  'input_name_for multiple_uploads' =>
 *                      [ 0 => ['name', 'full_path', 'type', 'tmp_name', 'error', 'size'],
 *                        1 => ['name', 'full_path', 'type', 'tmp_name', 'error', 'size'] ]
 *                ]
 * therefore, after creating an instance of the class 
 * and checking the existence of the input data, 
 * there are always two foreach, 
 * and then $this->execute($input, $key, $file)
 * 
 * eg
 * $upload = new FIUP\File_upload; 
 * if ($upload->isset_data()) 
 * {
 *  foreach ($load->files as $input => $input_array) 
 *  {
 *      print 'Input "'.$input.'":<br />';
 *      // SET the vars for class
 *      if ($input === 'file') 
 *      {
 *          $upload->propeties = ''; 
 *      }
 *      foreach ($input_array as $key => $file) 
 *      {
 *          print 'Name "'.$file['name'].'":<br />';
 *          // PROCESSING DATA
 *          if ($load->execute($input, $key, $file) && !empty($load->message)) 
 *          {
 *              print $load->message; print '<br />';
 *          } 
 *          else 
 *          {
 *              if (!empty($load->error)) 
 *              {
 *                  print $load->error; print '<br />';
 *              }
 *              continue;
 *          }
 *      }
 *   }
 * }
 * 
 * $this->isset_data(): check if $this->files not empty (this means in $_FILES is also not empty)
 * 
 * $this->execute():
 * check input data: dest_dir required
 * check error: in FILES
 * check_dest_dir: $this->create_dir default false, $this->dir_permissions default 0755;
 * check_file_size: if user not set $this->file_size - default 1024000B (1MB), set in bytes eg 2*100*1024 (200KB)
 * check_mime_type: if user not set $this->file_mimetype -default any, 
 *      string or array, 'audio' or ['image/bmp', 'audio', 'video'],
 *      if user set full mimetype eg imge/bmp - the class also check the extension
 * check_extension: if user not set $this->file_ext -default any, string or array, eg 'jpg', ['.png', '.webp', 'jpeg']
 * check_new_file_name: use $this->translit_ostslav_to_lat, for other - replace with $this->translit_to_lat
 * move_upload: upload file to dir (dir = tmp dir if user set $this->processing or $this->tmp_dir, else - dir = dest dir)
 * check_processing: check if isset array $this->processing and this is associative and not empty
 * img_proc
 * 
 * this __destruct clear tmp folder;
 * 
 */

namespace Fiup;

class File_upload
{
    // user-defined class properties
    public string $dest_dir;
    public int $dir_permissions;
    public int $file_permissions;
    public bool $create_dir;
    public int $file_size;
    public $file_mimetype;
    public $file_ext;
    public string $new_file_name;
    public bool $replace_old_file;
    public string $tmp_dir;
    public array $processing;
    // other properties
    public array $files;
    public array $phpFileUploadErrors;
    public string $message;
    public string $error;
    protected array $errors;
    protected string $name;
    

    function __construct() {
        $this->files = self::normalize_files_array();  
        $this->default_vars();      
    }

    public function default_vars() {
        //declaring variables
        $this->phpFileUploadErrors = array(
            0 => 'Success! The file uploaded.',
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            3 => 'The uploaded file was only partially uploaded.',
            4 => 'No file was uploaded.',
            6 => 'Missing a temporary folder.',
            7 => 'Failed to write file to disk.',
            8 => 'A PHP extension stopped the file upload.',
        );
        $this->errors = [
            0 => 'Define the destination directory.',
            1 => 'UNKNOWN ERROR!',
            2 => 'Directory is not writable and chmod return false.',
            3 => 'This is file, not directory.',
            4 => 'Failed to create directory.',
            5 => 'Directory does not exist and cannot be created because $this->create_dir = false.',
            6 => '$this->dest_dir is empty for class Upload.',
            7 => '"size" from $_FILES is empty.',
            8 => 'Size is too large.',
            9 => 'Wrong mimetype.',
            10 => 'Wrong $this->file_mimetype, must be empty, string or array.',
            11 => 'Wrong extension.',
            12 => 'Wrong type in input data "file_ext", must be empty, string or array.',
            13 => 'Value "name" from $_FILES is empty.',
            14 => 'A file with that name exists in the directory.',
            15 => 'Possible file upload attack.',

        ];
        $this->message = '';
        $this->error = '';
        $this->dest_dir = '';
        $this->dir_permissions = 0755;
        $this->file_permissions = 0644;
        $this->create_dir = false;
        //$this->file_size = 1024000; // set in check_file_size
        $this->file_mimetype = '';
        $this->file_ext = '';
        $this->new_file_name = '';
        $this->replace_old_file = false;
        $this->tmp_dir = '';
        $this->processing = [];
    }

    public function isset_data() {
        return (is_array($this->files) && !empty($this->files)) ? true : false;
    }

    public function execute($input_array, $key, $file) {
        //checking the variables set by the user dest dir and tmp dir
        if (empty($this->dest_dir)) {
            $this->error = 'ERROR!<br />'. $this->errors[0] .'<br />';
            return false;
        } else {
            // del ending lash in dir
            $this->dest_dir = rtrim($this->dest_dir, DIRECTORY_SEPARATOR);
            if (!empty($this->tmp_dir)) {
                $this->tmp_dir = rtrim($this->tmp_dir, DIRECTORY_SEPARATOR);
            }
        }
        //check error in FILES
        if ($file['error'] !== 0 && $file['error'] !== '0' && $file['error'] !== 'UPLOAD_ERR_OK') {
            if (array_key_exists($file['error'], $this->phpFileUploadErrors)) {
                $this->error = 'ERROR!<br />'.$this->phpFileUploadErrors[$file['error']];
            } else {
                $this->error = $this->errors[1] .'<br />';
            }
            return false;
        }
        // check_dest_dir
        if ($this->check_dest_dir() === false ) {
            return false;
        }
        // check_file_size
        if ($this->check_file_size($file['size']) === false ) {
            return false;
        }
        // check_mime_type
        if ($this->check_mime_type($file['name'], $file['tmp_name']) === false ) {
            return false;
        }
        // check_extension
        if ($this->check_extension($file['name'], $file['tmp_name']) === false ) {
            return false;
        }
        
        // check_new_file_name
        if ($this->check_new_file_name($input_array, $key, $file) === false ) {
            return false;
        }
        // move_upload
        if ($this->move_upload($file['tmp_name']) === false ) {
            return false;
        } 
        // check_processing
        if ($this->check_processing()) {
            // img_proc
            if ($this->img_proc() === false ) {
                return false;
            }
        }
        return true;
    }
   /**
     * delete all files into directory
     * @return bool
     */
    public function del_files_in_dir(string $dir, bool $recursive = true) {
        if (!empty($dir)) {
            $dir = rtrim($dir, DIRECTORY_SEPARATOR);
            if (!is_readable($dir)) {
                $this->error = 'ERROR!<br />Not unlink files in tmp dir, because dir is not readable "'.$dir.'".<br />';
                return false;
            }
            $files = array_diff(scandir($dir), array('.','..'));
            foreach ($files as $file) {
              if (is_dir($dir.DIRECTORY_SEPARATOR.$file) && $recursive === true) {
                $this->del_files_in_dir($dir.DIRECTORY_SEPARATOR.$file, true);
              } else {
                if (!unlink($dir.DIRECTORY_SEPARATOR.$file)) {
                    $this->error = 'ERROR!<br />Not unlink "'.$dir/$file.'".<br />';
                    return false;
                } else {
                  return true;
                }
              }
            }
        }
    }
    /**
     * use class \Fiup\Imageresize\Imageresize
     * @return bool
     */
    public function img_proc() {
        $file = $this->tmp_dir.DIRECTORY_SEPARATOR.$this->new_file_name;
        try{
            $imageresize = new \Fiup\Imageresize\Imageresize($file);
            foreach ($this->processing as $method => $value) {
                if ( method_exists($imageresize,$method) ) {
                    if (is_array($value) && $this->count_parameters_of_method($imageresize, $method) == count($value)) {
                        call_user_func_array(array($imageresize, $method), $value); //$value - array of parameters
                    } else {
                        $this->error = 'ERROR!<br />The "processing" value of "'.$method.'" is not array, or wrong numbers key of array (parameters for method of class Imageresize)';
                        return false;
                    }
                } else {
                    $this->error = 'ERROR!<br />Method "'.$method.'" not exists in class App\Lib\Imageresize';
                    return false;
                }
            }
            $imageresize->save($this->dest_dir.DIRECTORY_SEPARATOR.$this->new_file_name);
            chmod($this->dest_dir.DIRECTORY_SEPARATOR.$this->new_file_name , $this->file_permissions);
            $this->message .= 'SUCCES!<br />File has been processed and copied to <br />"'.$this->dest_dir.DIRECTORY_SEPARATOR.$this->new_file_name.'".<br />';
            return true;
        } catch (\Fiup\Imageresize\Imageresizeexception $e) {
            $this->error = "ERROR!<br />Something went wrong.<br />" . $e->getMessage();
            return false;
        }
    }

    function count_parameters_of_method($class, $method)
    {
        $action_method_relfection = new \ReflectionMethod($class, $method);
        return $action_method_relfection->getNumberOfRequiredParameters();
    }
    /**
     * upload file to tmp dir or dest dir
     * @param string $file_tmp_name (from $_FILES['tmp_name])
     * @return bool
     */

    protected function move_upload($file_tmp_name) {
        $dir = $this->tmp_dir;
        if ( !$this->check_processing() ) { 
            if ( empty($this->tmp_dir) ) {
                $dir = $this->dest_dir.DIRECTORY_SEPARATOR;
            } 
        }
        if ( $this->check_or_create_dir($dir, $this->dir_permissions, $this->create_dir) === false ) {
            return false;
        }

        if (move_uploaded_file($file_tmp_name, $dir.DIRECTORY_SEPARATOR.$this->new_file_name)) {
            chmod($dir.DIRECTORY_SEPARATOR.$this->new_file_name , $this->file_permissions);
            $this->message .= 'File is uploaded to: "'.$dir.DIRECTORY_SEPARATOR.$this->new_file_name.'".<br />'; 
            return true;
        } else {
            $this->error = 'ERROR!<br />'.$this->errors[15] .'<br />';
            return false;
        }
    }
    /**
     * check if file post processing isset
     * @return bool
     */
    protected function check_processing() {
        if (empty($this->processing)) {
            return false;
        } else { 
            if ( is_array($this->processing) && $this->isAssoc($this->processing) &&!empty($this->processing) ) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function isAssoc(array $arr)
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    /**
     * set $this->new_file_name
     * @param array $input_array - array of all files from input
     * @param int $key - key of file in $input_array
     * @param array $file (a file array from $_FILES['input])
     * @return bool
     */
    protected function check_new_file_name($input_array, $key, $file) {
        if ($this->new_name($input_array, $key, $file)) {
            $new_name = $this->name.$this->get_point_ext($file['name']);
            if (file_exists($this->dest_dir.DIRECTORY_SEPARATOR.$new_name)) {
                if ($this->replace_old_file) {
                    $this->new_file_name = $new_name; 
                    return true;
                } else {
                    $this->error = 'ERROR!<br /> 
                                        Change $this->new_file_name for upload class in model or set $this->replace_old_file = true,<br />
                                        because '. $this->errors[14] .'<br />
                                        File: "'.$new_name.'", dir: "'.$this->dest_dir.'".<br />';
                    return false;
                }
            } else {
                $this->new_file_name = $new_name;
                return true;
            }
        } else {
            return false;
        }
    }
    /**
     * create sanitize name from users input, set $this->name
     * @param array $input_array - array of all files from input
     * @param int $key - key of file in $input_array
     * @param array $file (a file array from $_FILES['input])
     * @return bool
     */
    protected function new_name($input_array, $key, $file) {
        //get patrs of files name
        if (!empty($file['name'])) {
            $path_parts = pathinfo($file['name']);
        } else {
            $this->error = 'ERROR!<br />'. $this->errors[13] .'<br />';
            return false;
        }
        //sanitize filename or create filename from old filename
        if (empty($this->new_file_name)) {
            //create new file name
                $this->name = $this->sanitize_string($this->translit_ostslav_to_lat($path_parts['filename']));
                return true;
        } else {
            if (count($input_array) > 1) {
                $this->name = pathinfo($key.'_'.$this->sanitize_string($this->translit_ostslav_to_lat($this->new_file_name)), PATHINFO_FILENAME);
                return true;
            } else {
                $name = $this->sanitize_string($this->translit_ostslav_to_lat($this->new_file_name));
                //del path and ext from name, if user set them by mistake
                $this->name = pathinfo($name, PATHINFO_FILENAME);
                return true;
            }
        }
    }
    /**
     * replaces all Cyrillic letters with Latin
     * @param string $var
     * @return string
     */
    function translit_ostslav_to_lat($textcyr) {
        $cyr = ['Ц','ц', 'а','б','в','ў','г','ґ','д','е','є','ё','ж','з','и','ï','й','к','л','м','н','о','п', 'р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я', 'А','Б','В','Ў','Г','Ґ','Д','Е','Є','Ё','Ж','З','И','Ї','Й','К','Л','М','Н','О','П', 'Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'
        ];
        $lat = ['C','c', 'a','b','v','w','g','g','d','e','ye','io','zh','z','i','yi','y','k','l','m','n','o','p', 'r','s','t','u','f','h','ts','ch','sh','sht','a','i','y','e','yu','ya', 'A','B','V','W','G','G','D','E','Ye','Io','Zh','Z','I','Yi','Y','K','L','M','N','O','P', 'R','S','T','U','F','H','Ts','Ch','Sh','Sht','A','I','Y','e','Yu','Ya'
        ];
        $textlat = str_replace($cyr, $lat, $textcyr);
        return $textlat;
    }
     /**
     * replaces all letters with Latin ASCII
     * @param string $var
     * @return string
     */
    function translit_to_lat($text) {
        $res = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", transliterator_transliterate('Any-Latin; Latin-ASCII', $text));
        return $res;
    }
    /**
     * converts all characters in a string to safe for processing
     * @param string $var
     * @return string or false
     */
    public function sanitize_string($var) 
    {
        if (is_string($var) && !empty($var)) {
            // remove HTML tags
            $var = strip_tags($var);
            // remove non-breaking spaces
            $var = preg_replace("#\x{00a0}#siu", ' ', $var);
            // remove illegal file system characters
            $var = str_replace(array_map('chr', range(0, 31)), '', $var);
            // remove dangerous characters for file names
            $chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "’", "%20",
                        "+", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}", "%", "+", "^", chr(0));
            $var = str_replace($chars, '_', $var);
            // remove break/tabs/return carriage
            $var = preg_replace('/[\r\n\t -]+/', '_', $var);
            // convert some special letters
            $convert = array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss',
                            'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u' );
            $var = strtr($var, $convert);
            // remove foreign accents by converting to HTML entities, and then remove the code
            $var = html_entity_decode( $var, ENT_QUOTES, "utf-8" );
            $var = htmlentities($var, ENT_QUOTES, "utf-8");
            $var = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $var);
            // clean up, and remove repetitions
            $var = preg_replace('/_+/', '_', $var);
            $var = preg_replace(array('/ +/', '/-+/'), '_', $var);
            $var = preg_replace(array('/-*\.-*/', '/\.{2,}/'), '.', $var);
            // cut to 255 characters
            $var = substr($var, 0, 255);
            // remove bad characters at start and end
            $var = trim($var, '.-_');
            return $var;
        } else {
            return false;
        }
    }
    /**
     * check mimetype of file
     * @param string $filename (from $_FILES['name])
     * @param string $file_tmp_name (from $_FILES['tmp_name])
     * @return bool
     */
    protected function check_mime_type($file_name, $file_tmp_name) {
        if (!empty($file_tmp_name)) {
            $mt = $this->get_mime_type($file_tmp_name);
            list($core, $ext) = explode('/', $mt);
            if ( empty($this->file_mimetype) ) {
                return true;
            } else {
                if (is_string($this->file_mimetype)) {
                    if ((!empty($core) && $this->file_mimetype === $core) || $this->file_mimetype === $mt) {
                        return true;
                    } else {
                        $this->error .= 'ERROR!<br />'.$this->errors[9].' File mimetype is "'.$mt.'", expected "'.$this->file_mimetype.'".<br />';
                        return false;
                    }
                } else {
                    if (is_array($this->file_mimetype)) {
                        if ( (!empty($core) && in_array($core, $this->file_mimetype)) || in_array($mt, $this->file_mimetype) ) {
                            return true;
                        } else {
                            $this->error .= 'ERROR!<br />'.$this->errors[9].' File mimetype is "'.$mt.'", expected "'.implode('", "', $this->file_mimetype).'".<br />';
                            return false;
                        }
                    } else {
                        $this->error .= 'ERROR!<br />'.$this->errors[10].'<br />';
                        return false;
                    }
                }
            }
            if (!empty($ext)) {
                if ($this->check_extension($file_name, $file_tmp_name)) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
	}

    /**
     * @param string $filename (from $_FILES['name])
     * @param string $file_tmp_name (from $_FILES['tmp_name])
     * @return bool
     */
    protected function check_extension($file_name, $file_tmp_name) {
        if (empty($this->file_ext)) {
            return true;
        } else {
            $ext = $this->get_extension($file_name);
            $pext = $this->get_point_ext($file_name);
            $mt = $this->get_mime_type($file_tmp_name);
            // crutch for jpg
            if ( ($mt === "image/jpeg" or $mt === "image/pjpeg") && $ext === 'jpg' ) {
                $r = true;
            } else {
                if ($this->mime2ext($mt) === $ext) {
                    $r = true;
                } else {
                    $r = false;
                }
            }
            if ($r) {
                if (is_string($this->file_ext) && ($ext === $this->file_ext || $pext === $this->file_ext)) {
                    return true;
                } elseif (is_array($this->file_ext)) {
                    if (in_array($ext, $this->file_ext) || in_array($pext, $this->file_ext)) {
                        return true;
                    } else {
                        $this->error = 'ERROR!<br />'.$this->errors[11].' File ext is "'.$ext.'", expected "'.implode('", "', $this->file_ext).'".<br />';
                        return false;
                    }
                } else {
                    $this->error = 'ERROR!<br />'.$this->errors[12].'<br />';
                    return false;
                }
            } else {
                $this->error = 'ERROR!<br />'.$this->errors[11].'" '.$ext.'", because mimetype of uploaded file is: "'.$mt.'".<br />';
                return false;
            }
        }
	}
    /**
    * @param string $filename
    * @return string file extension without a dot at the beginning
    */
    public function get_extension($filename) {
		//$ext = strtolower(mb_substr(strrchr($filename, '.'), 1));
        $path_info = pathinfo($filename);
        $ext = strtolower($path_info['extension']);
		return $ext;
	}
    /**
    * @param string $filename
    * @return string file extension with a dot at the beginning
    */
    public function get_point_ext($filename) {
		$ext = strtolower(strrchr($filename, '.'));
		return $ext;
	}
    /**
     * @param string $mimetype
     * @return string or false
     */
    public function mime2ext($mime) {
        $mime_map = [
            'video/3gpp2'                                                               => '3g2',
            'video/3gp'                                                                 => '3gp',
            'video/3gpp'                                                                => '3gp',
            'application/x-compressed'                                                  => '7zip',
            'audio/x-acc'                                                               => 'aac',
            'audio/ac3'                                                                 => 'ac3',
            'application/postscript'                                                    => 'ai',
            'audio/x-aiff'                                                              => 'aif',
            'audio/aiff'                                                                => 'aif',
            'audio/x-au'                                                                => 'au',
            'video/x-msvideo'                                                           => 'avi',
            'video/msvideo'                                                             => 'avi',
            'video/avi'                                                                 => 'avi',
            'application/x-troff-msvideo'                                               => 'avi',
            'application/macbinary'                                                     => 'bin',
            'application/mac-binary'                                                    => 'bin',
            'application/x-binary'                                                      => 'bin',
            'application/x-macbinary'                                                   => 'bin',
            'image/bmp'                                                                 => 'bmp',
            'image/x-bmp'                                                               => 'bmp',
            'image/x-bitmap'                                                            => 'bmp',
            'image/x-xbitmap'                                                           => 'bmp',
            'image/x-win-bitmap'                                                        => 'bmp',
            'image/x-windows-bmp'                                                       => 'bmp',
            'image/ms-bmp'                                                              => 'bmp',
            'image/x-ms-bmp'                                                            => 'bmp',
            'application/bmp'                                                           => 'bmp',
            'application/x-bmp'                                                         => 'bmp',
            'application/x-win-bitmap'                                                  => 'bmp',
            'application/cdr'                                                           => 'cdr',
            'application/coreldraw'                                                     => 'cdr',
            'application/x-cdr'                                                         => 'cdr',
            'application/x-coreldraw'                                                   => 'cdr',
            'image/cdr'                                                                 => 'cdr',
            'image/x-cdr'                                                               => 'cdr',
            'zz-application/zz-winassoc-cdr'                                            => 'cdr',
            'application/mac-compactpro'                                                => 'cpt',
            'application/pkix-crl'                                                      => 'crl',
            'application/pkcs-crl'                                                      => 'crl',
            'application/x-x509-ca-cert'                                                => 'crt',
            'application/pkix-cert'                                                     => 'crt',
            'text/css'                                                                  => 'css',
            'text/x-comma-separated-values'                                             => 'csv',
            'text/comma-separated-values'                                               => 'csv',
            'application/vnd.msexcel'                                                   => 'csv',
            'application/x-director'                                                    => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/x-dvi'                                                         => 'dvi',
            'message/rfc822'                                                            => 'eml',
            'application/x-msdownload'                                                  => 'exe',
            'video/x-f4v'                                                               => 'f4v',
            'audio/x-flac'                                                              => 'flac',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/gpg-keys'                                                      => 'gpg',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'application/mac-binhex40'                                                  => 'hqx',
            'application/mac-binhex'                                                    => 'hqx',
            'application/x-binhex40'                                                    => 'hqx',
            'application/x-mac-binhex40'                                                => 'hqx',
            'text/html'                                                                 => 'html',
            'image/x-icon'                                                              => 'ico',
            'image/x-ico'                                                               => 'ico',
            'image/vnd.microsoft.icon'                                                  => 'ico',
            'text/calendar'                                                             => 'ics',
            'application/java-archive'                                                  => 'jar',
            'application/x-java-application'                                            => 'jar',
            'application/x-jar'                                                         => 'jar',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'application/x-javascript'                                                  => 'js',
            'application/json'                                                          => 'json',
            'text/json'                                                                 => 'json',
            'application/vnd.google-earth.kml+xml'                                      => 'kml',
            'application/vnd.google-earth.kmz'                                          => 'kmz',
            'text/x-log'                                                                => 'log',
            'audio/x-m4a'                                                               => 'm4a',
            'application/vnd.mpegurl'                                                   => 'm4u',
            'audio/midi'                                                                => 'mid',
            'application/vnd.mif'                                                       => 'mif',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/oda'                                                           => 'oda',
            'application/vnd.oasis.opendocument.text'                                   => 'odt',
            'application/vnd.oasis.opendocument.spreadsheet'                            => 'ods',
            'application/vnd.oasis.opendocument.presentation'                           => 'odp',
            'audio/ogg'                                                                 => 'ogg',
            'video/ogg'                                                                 => 'ogg',
            'application/ogg'                                                           => 'ogg',
            'application/x-pkcs10'                                                      => 'p10',
            'application/pkcs10'                                                        => 'p10',
            'application/x-pkcs12'                                                      => 'p12',
            'application/x-pkcs7-signature'                                             => 'p7a',
            'application/pkcs7-mime'                                                    => 'p7c',
            'application/x-pkcs7-mime'                                                  => 'p7c',
            'application/x-pkcs7-certreqresp'                                           => 'p7r',
            'application/pkcs7-signature'                                               => 'p7s',
            'application/pdf'                                                           => 'pdf',
            'application/octet-stream'                                                  => 'pdf',
            'application/x-x509-user-cert'                                              => 'pem',
            'application/x-pem-file'                                                    => 'pem',
            'application/pgp'                                                           => 'pgp',
            'application/x-httpd-php'                                                   => 'php',
            'application/php'                                                           => 'php',
            'application/x-php'                                                         => 'php',
            'text/php'                                                                  => 'php',
            'text/x-php'                                                                => 'php',
            'application/x-httpd-php-source'                                            => 'php',
            'image/png'                                                                 => 'png',
            'image/x-png'                                                               => 'png',
            'application/powerpoint'                                                    => 'ppt',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.ms-office'                                                 => 'ppt',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'audio/x-realaudio'                                                         => 'ra',
            'audio/x-pn-realaudio'                                                      => 'ram',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'audio/x-pn-realaudio-plugin'                                               => 'rpm',
            'application/x-pkcs7'                                                       => 'rsa',
            'text/rtf'                                                                  => 'rtf',
            'text/richtext'                                                             => 'rtx',
            'video/vnd.rn-realvideo'                                                    => 'rv',
            'application/x-stuffit'                                                     => 'sit',
            'application/smil'                                                          => 'smil',
            'text/srt'                                                                  => 'srt',
            'image/svg+xml'                                                             => 'svg',
            'application/x-shockwave-flash'                                             => 'swf',
            'application/x-tar'                                                         => 'tar',
            'application/x-gzip-compressed'                                             => 'tgz',
            'image/tiff'                                                                => 'tiff',
            'text/plain'                                                                => 'txt',
            'text/x-vcard'                                                              => 'vcf',
            'application/videolan'                                                      => 'vlc',
            'text/vtt'                                                                  => 'vtt',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'application/wbxml'                                                         => 'wbxml',
            'video/webm'                                                                => 'webm',
            'audio/x-ms-wma'                                                            => 'wma',
            'application/wmlc'                                                          => 'wmlc',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'application/xhtml+xml'                                                     => 'xhtml',
            'application/excel'                                                         => 'xl',
            'application/msexcel'                                                       => 'xls',
            'application/x-msexcel'                                                     => 'xls',
            'application/x-ms-excel'                                                    => 'xls',
            'application/x-excel'                                                       => 'xls',
            'application/x-dos_ms_excel'                                                => 'xls',
            'application/xls'                                                           => 'xls',
            'application/x-xls'                                                         => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-excel'                                                  => 'xlsx',
            'application/xml'                                                           => 'xml',
            'text/xml'                                                                  => 'xml',
            'text/xsl'                                                                  => 'xsl',
            'application/xspf+xml'                                                      => 'xspf',
            'application/x-compress'                                                    => 'z',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
            'text/x-scriptzsh'                                                          => 'zsh',
        ];
        return isset($mime_map[$mime]) === true ? $mime_map[$mime] : false;
    }
    /**
     * get mimetype of file
     * @param string $file
     * @return string or false
     */
    function get_mime_type(string $file) {
		$mtype = false;
		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mtype = finfo_file($finfo, $file);
			finfo_close($finfo);
		} elseif (function_exists('mime_content_type')) {
			$mtype = mime_content_type($file);
		} 
		return $mtype;
	}
    /**
     * check size of upload file, set $this->file_size
     * @param int $size_from_this_files
     * @return bool
     */
    protected function check_file_size(int $size_from_this_files) {
        if (!empty($size_from_this_files)) {
            $size = (!empty($this->file_size) && is_numeric($this->file_size)) ? $this->file_size : 1024000;
            if ( $size_from_this_files <= $size) {
               return true;
            } else {
                $this->error = 'ERROR!<br />' . $this->errors[8] .'<br />';;
                return false;
            }
        } else {
            $this->error = 'ERROR!<br />' . $this->errors[7] .'<br />';
            return false;
        }
    }
    /**
     * check if destination dir exists
     * @return bool
     */
    public function check_dest_dir() {
        if (!is_string($this->dest_dir)) {
            $this->error = 'ERROR! ' . $this->errors[6].'<br />';
            return false;
        }
        if ( $this->check_or_create_dir($this->dest_dir, $this->dir_permissions, $this->create_dir) ) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * check if dir exists, writable, if not - create dir
     * @param string $dir
     * @param int $dir_permissions - permissions for dir
     * @param bool $create - create dir if not exists
     * @return bool
     */
    public function check_or_create_dir(string $dir, int $dir_permissions, bool $create) {
            if (file_exists($dir)) {
                if (is_dir($dir)) {
                    if ( is_writable($dir)) {
                        return true;
                    } else {
                        if (chmod($dir, $dir_permissions)) {
                            return true;
                        } else {
                            $this->error = 'ERROR! Dir "'.$dir.'": ' . $this->errors[2].'<br />';
                            return false;
                        }
                    }
                } else {
                    $this->error = 'ERROR! Dir "'.$dir.'": ' . $this->errors[3].'<br />';
                    return false;
                }
            } else {
                # create dir if $create_dir = true or message - dir not exists
                if ($create) {
                    if (mkdir($dir, $dir_permissions, true)) {
                        return true;
                    } else {
                        $this->error = 'ERROR! Dir "'.$dir.'": ' . $this->errors[4].'<br />';
                        return false;
                    }
                } else {
                    $this->error = 'ERROR! Dir "'.$dir.'": ' . $this->errors[5].'<br />';
                    return false;
                }
            }
    }
    /**
     * check if $_FILES exists and normalize it
     * @return array
     */
    public static function normalize_files_array() {
        $normalized_array = [];
        if (isset($_FILES)) {
            foreach($_FILES as $index => $file) {
                if (!is_array($file['name'])) {
                    if (!empty($file['name'])) {
                        $normalized_array[$index][] = $file;
                        continue;
                    }
                }
                if (!empty($file['name'])) {
                    foreach($file['name'] as $idx => $name) {
                        if (!empty($name)) {
                            $normalized_array[$index][$idx] = [
                                'name' => $name,
                                'type' => $file['type'][$idx],
                                'tmp_name' => $file['tmp_name'][$idx],
                                'error' => $file['error'][$idx],
                                'size' => $file['size'][$idx]
                            ];
                        }
                    }
                }
            }
        }
        return $normalized_array;
    }
}

?>