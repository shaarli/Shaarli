/* global YT */
const pluginInstances = {};
const $input = document.querySelector('#playvideos');

class PlayVideos {
  constructor() {
    this.links = [];
    this.ytInstance = null;

    this.hosts = ['youtube.com', 'youtu.be'];

    this.getAllVideos();
    this.createDom();

    if (!document.head.querySelector('script[src*="//www.youtube.com/iframe_api"]')) {
      this.getScript('https://www.youtube.com/iframe_api');
    } else {
      this.initiateInstance();
    }

    window.onYouTubeIframeAPIReady = this.initiateInstance.bind(this);
  }

  getAllVideos() {
    const ids = [];
    document.querySelectorAll('a[href^="http"]').forEach(($elm) => {
      // checks if is part of hosts
      if (!this.hosts.some((u) => $elm.getAttribute('href').includes(u))) {
        return;
      }
      // checks if it is a valid URl
      const url = URL.canParse($elm.href) && new URL($elm.href);
      if (!url) {
        return;
      }
      // switch for index of this.hosts
      switch (this.hosts.indexOf(url.host.replace('www.', ''))) {
        case 0:
          if (url.searchParams.get('v')) {
            ids.push(url.searchParams.get('v'));
          }
          break;
        case 1:
          if (url.pathname) {
            ids.push(url.pathname.replace('/', ''));
          }
          break;
        default:
          break;
      }
    }, this);

    // remove duplicates
    this.links = ids.filter((item, pos) => ids.indexOf(item) === pos);
  }

  createDom() {
    const $bg = document.createElement('div');
    $bg.id = 'player-background';
    $bg.style.position = 'fixed';
    $bg.style.top = 0;
    $bg.style.left = 0;
    $bg.style.display = 'flex';
    $bg.style.justifyContent = 'center';
    $bg.style.alignItems = 'center';
    $bg.style.zIndex = 1001;
    $bg.style.width = '100%';
    $bg.style.height = '100%';
    $bg.style.backgroundColor = 'rgba(0,0,0,.8)';
    $bg.tabIndex = 0;
    $bg.addEventListener('keyup', this.keybControl.bind(this));
    $bg.addEventListener('click', this.destroyInstance.bind(this));

    const $box = document.createElement('div');
    $box.id = 'player-content';

    const $boxFrame = document.createElement('div');
    $boxFrame.id = 'player-frame';
    $box.appendChild($boxFrame);

    const $playerNavigation = document.createElement('div');
    $playerNavigation.id = 'player-navigation';
    $playerNavigation.style.display = 'flex';
    $playerNavigation.style.justifyContent = 'space-between';
    $playerNavigation.style.alignItems = 'center';

    const $prev = document.createElement('button');
    $prev.innerText = 'previous';
    $prev.dataset.dir = 'prev';
    $prev.addEventListener('click', this.nextPrev.bind(this));

    $playerNavigation.appendChild($prev);

    const $next = document.createElement('button');
    $next.innerText = 'next';
    $next.dataset.dir = 'next';
    $next.addEventListener('click', this.nextPrev.bind(this));
    $playerNavigation.appendChild($next);

    $box.appendChild($playerNavigation);

    $bg.appendChild($box);

    document.querySelector('html').appendChild($bg);
    $bg.focus();
  }

  keybControl(e) {
    switch (e.keyCode) {
      case 27: // esc
        this.destroyInstance();
        break;
      case 39: // right
        document.querySelector('#player-navigation button[data-dir="next"]').click();
        break;
      case 37: // left
        document.querySelector('#player-navigation button[data-dir="prev"]').click();
        break;
      default:
        break;
    }
  }

  nextPrev(e) {
    e.preventDefault();
    e.stopPropagation();
    const { dir } = e.target.dataset;
    const currentIndex = this.links.findIndex((i) => i === this.ytInstance.getVideoData().video_id);

    if (dir === 'next' && this.links[currentIndex + 1]) {
      this.ytInstance.loadVideoById(this.links[currentIndex + 1]);
    } else if (dir === 'next') {
      this.ytInstance.loadVideoById(this.links[0]);
    }

    if (dir === 'prev' && this.links[currentIndex - 1]) {
      this.ytInstance.loadVideoById(this.links[currentIndex - 1]);
    } else if (dir === 'prev') {
      this.ytInstance.loadVideoById(this.links[this.links.length - 1]);
    }
  }

  async getScript(url) {
    const urlPromise = await new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = url;
      script.async = true;
      script.onerror = reject;
      script.onreadystatechange = () => {
        const loadState = this.readyState;
        if (loadState && loadState !== 'loaded' && loadState !== 'complete') return;
        script.onload = script.onreadystatechange || null;
        resolve();
      };
      script.onload = script.onreadystatechange;
      document.head.appendChild(script);
    });
    return urlPromise;
  }

  destroyInstance() {
    this.ytInstance.destroy();
    document.querySelector('#player-background').remove();
    delete (pluginInstances.instance);
  }

  initiateInstance() {
    this.ytInstance = new YT.Player('player-frame', {
      height: '390',
      width: '640',
      videoId: this.links[0],
      events: {
        onReady: this.ytReady,
        onError: this.ytError,
        onStateChange: this.ytStateChange,
      },
    });
  }

  /* eslint-disable class-methods-use-this */
  ytReady(e) {
    e.target.playVideo();
  }

  ytError(e) {
    const errors = {
      2: 'invalid video id',
      5: 'video not supported in html5',
      100: 'video removed or private',
      101: 'video not embedable',
      150: 'video not embedable',
    };
    console.log('Error', errors[e.data] || 'unknown error');
    document.querySelector('#player-navigation button[data-dir="next"]').click();
  }

  ytStateChange(e) {
    if (e.data === window.YT.PlayerState.ENDED) {
      document.querySelector('#player-navigation button[data-dir="next"]').click();
    }
  }
  /* eslint-enable class-methods-use-this */
}

$input.addEventListener('click', (e) => {
  e.preventDefault();
  pluginInstances.instance = new PlayVideos();
});
