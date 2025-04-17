let pluginInstances = {};
const $input = document.querySelector('#playvideos');
$input.addEventListener('click', (e) => {
  e.preventDefault();
  pluginInstances.instance = new PlayVideos();
});

class PlayVideos {
  
  links = [];
  ytInstance;

  constructor() {
    this.getAllVideos();
    this.createDom();

    if (!document.head.querySelector('script[src="//www.youtube.com/iframe_api"]')) {
      this.getScript('//www.youtube.com/iframe_api');
    } else {
      this.initiateInstance();
    }

    window.onYouTubeIframeAPIReady = this.initiateInstance;
  }

  getAllVideos = () => {
    const reg = new RegExp("https?://(www.)?youtube.com/");
    const ids = [];
    document.querySelectorAll('a[href^="http"]').forEach(($elm) => {
      if (!$elm.getAttribute("href").match(reg)) {
        return
      }
      const n = $elm.href.replace(/^.*v=/, "").replace(/\&.*$/, "");
      ids.push(n);
    });
    
    // remove duplicates
    this.links = ids.filter(function(item, pos) {
      return ids.indexOf(item) == pos;
    })
  }

  createDom = () => {
    let $bg = document.createElement('div');
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
    $bg.addEventListener('keyup', this.keybControl);
    $bg.addEventListener('click', this.destroyInstance);

    let $box = document.createElement('div');
    $box.id = 'player-content';

    let $boxFrame = document.createElement('div');
    $boxFrame.id = 'player-frame';
    $box.appendChild($boxFrame);

    let $playerNavigation = document.createElement('div');
    $playerNavigation.id = 'player-navigation';
    $playerNavigation.style.display = 'flex';
    $playerNavigation.style.justifyContent = 'space-between';
    $playerNavigation.style.alignItems = 'center';

    let $prev = document.createElement('button');
    $prev.innerText = 'previous';
    $prev.dataset.dir = 'prev';
    $prev.addEventListener('click', this.nextPrev.bind(this));

    $playerNavigation.appendChild($prev);
    
    let $next = document.createElement('button');
    $next.innerText = 'next';
    $next.dataset.dir = 'next';
    $next.addEventListener('click', this.nextPrev.bind(this));
    $playerNavigation.appendChild($next);

    $box.appendChild($playerNavigation);

    $bg.appendChild($box);

    document.querySelector('html').appendChild($bg);
    $bg.focus();
  }

  keybControl = (e) => {
    switch (e.keyCode) {
      case 27: // esc
        this.destroyInstance();
        break;
      case 39: //right 
        document.querySelector('#player-navigation button[data-dir="next"]').click();
        break;
      case 37: //left 
        document.querySelector('#player-navigation button[data-dir="prev"]').click();
        break;
      default:
        break;
    }
  }

  nextPrev( e ) {
    e.preventDefault();
    e.stopPropagation();
    const dir = e.target.dataset.dir;
    const currentIndex = this.links.findIndex( (i) => i == this.ytInstance.getVideoData()['video_id']);
    
    if ( dir == 'next' && this.links[currentIndex + 1] ) {
      this.ytInstance.loadVideoById(this.links[currentIndex + 1]);
    } else if (dir == 'next') {
      this.ytInstance.loadVideoById(this.links[0]);
    }

    if ( dir == 'prev' && this.links[currentIndex - 1]) {
      this.ytInstance.loadVideoById(this.links[currentIndex - 1]);
    } else if (dir == 'prev') {
      this.ytInstance.loadVideoById(this.links[this.links.length - 1]);
    }
  }

  getScript = url => new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = url;
    script.async = true;
    script.onerror = reject;
    script.onload = script.onreadystatechange = function() {
      const loadState = this.readyState;
      if (loadState && loadState !== 'loaded' && loadState !== 'complete') return
      script.onload = script.onreadystatechange = null;
      resolve();
    }
    document.head.appendChild(script)
  })

  destroyInstance = () => {
    this.ytInstance.destroy()
    document.querySelector('#player-background').remove();
    delete(pluginInstances.instance);
  }

  initiateInstance = () => {
    this.ytInstance = new YT.Player('player-frame', {
      height: "390",
      width: "640",
      videoId: this.links[0],
      events: {
          onReady: this.ytReady,
          onError: this.ytError,
          onStateChange: this.ytStateChange
      }
    })
  }

  ytReady = (e) => {
    e.target.playVideo();
  }
  
  ytError = (e) => {
    const errors = {
      2: "invalid video id",
      5: "video not supported in html5",
      100: "video removed or private",
      101: "video not embedable",
      150: "video not embedable"
    };
    console.log("Error", errors[e.data] || "unknown error");
    document.querySelector('#player-navigation button[data-dir="next"]').click();
  }

  ytStateChange = (e) => {
    if (e.data === YT.PlayerState.ENDED) {
      document.querySelector('#player-navigation button[data-dir="next"]').click();
    }
  };

}
