#!/usr/bin/env php
<?php
class Venom_Application
{
    const VENOM_DIR = './.venom';
    const TMP_DIR   = './.venom/tmp';

    private $repos = array();

    private $vendor = './vendor';

    public static function run()
    {
        $app = new self;
        try {
            $app->initialize();
            $app->loadVenomfile();
            $app->download();
        } catch (Exception $e) {
            echo get_class($e) . ": {$e->getMessage()}", PHP_EOL;
        }
    }

    public function initialize()
    {
        if (!file_exists(self::VENOM_DIR)) {
            mkdir(self::VENOM_DIR);
        }
        if (file_exists(self::TMP_DIR)) {
            $this->cmd('rm', '-rf', self::TMP_DIR);
        }
        mkdir(self::TMP_DIR);
        if (!file_exists($this->vendor)) {
            mkdir($this->vendor);
        }
    }

    public function loadVenomfile()
    {
        if (file_exists('./Venomfile')) {
            $GLOBALS['venom'] = $this;
            include './Venomfile';
        } else {
            throw new RuntimeException('Venomfile not found');
        }
    }

    public function github($repo, $options = array())
    {
        $this->repos[] = new Venom_Repository_Github($repo, $options);
    }

    public function download()
    {
        foreach ($this->repos as $repo) {
            $this->cmd('wget', '--quiet', '-O', $this->tmp($repo->getTarGzFilename()), $repo->getTarGzUrl());
            mkdir($this->getTmpDir($repo));
            $this->cmd('tar', 'xzf', $this->tmp($repo->getTarGzFilename()), '--strip-components', '1', '-C', $this->tmp($repo->getHash()));
            $this->cmd('rm', '-rf', $this->getTargetDir($repo));
            $this->cmd('cp', '-pr', $this->getTmpDir($repo), $this->getTargetDir($repo));
        }
    }

    private function cmd()
    {
        $cmd = join(' ', array_map('escapeshellarg', func_get_args()));
        echo $cmd, PHP_EOL;
        echo `$cmd`;
    }

    private function getTmpDir(Venom_RepositoryInterface $repo)
    {
        return $this->tmp($repo->getHash());
    }

    private function getTargetDir(Venom_RepositoryInterface $repo)
    {
        return $this->vendor("{$repo->getUser()}-{$repo->getProject()}");
    }

    private function tmp($file)
    {
        return self::TMP_DIR . DIRECTORY_SEPARATOR . $file;
    }

    private function vendor($file)
    {
        return $this->vendor . DIRECTORY_SEPARATOR . $file;
    }
}

interface Venom_RepositoryInterface
{
    function getHash();
    function getUser();
    function getProject();
    function getTarGzFilename();
    function getTarGzUrl();
}

class Venom_Repository_Github implements Venom_RepositoryInterface
{
    private $url;

    private $user;

    private $project;

    private $branch;

    private $tag;

    public function __construct($url, $options = array())
    {
        $this->setUrl($url);
        $this->tag = isset($options['tag']) ? $options['tag'] : NULL;
        $this->branch = isset($options['branch']) ? $options['branch'] : NULL;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        if (preg_match('#^https://github.com/([^/]+)/([^/]+)#', $url, $matches)) {
            $this->user    = $matches[1];
            $this->project = $matches[2];
        } else {
            throw new RuntimeException('Invalid GitHub URL specified');
        }
    }

    public function getHash()
    {
        return md5($this->url);
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getProject()
    {
        return $this->project;
    }

    public function getTarGzFilename()
    {
        return "{$this->project}.tar.gz";
    }

    public function getTarGzUrl()
    {
        if (isset($this->tag)) {
            return "https://github.com/{$this->user}/{$this->project}/tarball/{$this->tag}";
        } else if (isset($this->branch)) {
            return "https://github.com/{$this->user}/{$this->project}/tarball/{$this->branch}";
        } else {
            return "https://github.com/{$this->user}/{$this->project}/tarball/master";
        }
    }
}

function github($url, $options = array()) {
    global $venom;
    $venom->github($url, $options);
}

Venom_Application::run();
