/** @licstart  The following is the entire license notice for the
 *  JavaScript code in this page.
 *
 *   Copyright: (c) 2011-2015 Sébastien SAUVAGE <sebsauvage@sebsauvage.net>
 *              (c) 2011-2017 The Shaarli Community, see AUTHORS
 *
 *   This software is provided 'as-is', without any express or implied warranty.
 *   In no event will the authors be held liable for any damages arising from
 *   the use of this software.
 *
 *   Permission is granted to anyone to use this software for any purpose,
 *   including commercial applications, and to alter it and redistribute it
 *   freely, subject to the following restrictions:
 *
 *   1. The origin of this software must not be misrepresented; you must not
 *   claim that you wrote the original software. If you use this software
 *   in a product, an acknowledgment in the product documentation would
 *   be appreciated but is not required.
 *
 *   2. Altered source versions must be plainly marked as such, and must
 *   not be misrepresented as being the original software.
 *
 *   3. This notice may not be removed or altered from any source distribution.
 *
 *  @licend  The above is the entire license notice
 *  for the JavaScript code in this page.
 */

var run_playideos = (function () {
    var e, n, t, o, r, i = [].indexOf || function (e) {
            for (var n = 0, t = this.length; n < t; n++) {
                if (n in this && this[n] === e) return n
            }
            return -1
        };
    if (!window.console) {
        window.console = {
            log: function () {}
        }
    }
    n = {
        shadow: {
            "background-color": "black",
            position: "fixed",
            left: 0,
            top: 0,
            width: "100%",
            height: "100%",
            "z-index": 1e3,
            opacity: .8
        },
        player_box: {
            position: "fixed",
            left: "50%",
            top: "50%",
            width: 640,
            height: 480,
            "margin-left": -320,
            "margin-top": -240,
            "z-index": 1001
        },
        prev_button: {
            "float": "left"
        },
        next_button: {
            "float": "right"
        }
    };
    t = function (e, n) {
        var t, o, r;
        r = document.createElement("script");
        r.src = e;
        o = document.getElementsByTagName("head")[0];
        t = false;
        r.onload = r.onreadystatechange = function () {
            var e, i;
            e = !this.readyState || (i = this.readyState) === "loaded" || i === "complete";
            if (!t && e) {
                t = true;
                n();
                r.onload = r.onreadystatechange = null;
                return o.removeChild(r)
            }
        };
        return o.appendChild(r)
    };
    e = function (e) {
        var t, o, r, a, u, l, d, c, f, p, s, y, h, g, v, m, w;
        e.getScript("//www.youtube.com/iframe_api");
        d = [];
        w = new RegExp("https?://(www.)?youtube.com/");
        e('a[href^="http"]').each(function () {
            var n;
            if (!e(this).attr("href").match(w)) {
                return
            }
            n = this.href.replace(/^.*v=/, "").replace(/\&.*$/, "");
            if (i.call(d, n) < 0) {
                return d.push(n)
            }
        });
        console.log("video ids", d);
        c = 0;
        y = null;
        g = "playlist_player";
        f = function () {
            console.log("Playing", c, d[c]);
            return y.loadVideoById(d[c])
        };
        p = function () {
            c++;
            if (c >= d.length) {
                c -= d.length
            }
            return f()
        };
        s = function () {
            c--;
            if (c < 0) {
                c += d.length
            }
            return f()
        };
        l = function () {
            e("#shadow, #player_box").remove();
            return e(document).unbind("keyup.player")
        };
        e(document).bind("keyup.player", function (e) {
            if (e.keyCode === 27) {
                l()
            }
            if (e.keyCode === 39) {
                p()
            }
            if (e.keyCode === 37) {
                return s()
            }
        });
        u = e("<div />", {
            id: "shadow",
            css: n.shadow,
            click: l
        });
        r = e("<div />", {
            id: "player_box",
            css: n.player_box
        });
        o = e("<div />", {
            id: g
        });
        a = e("<a />", {
            href: "javascript:;",
            text: "previous",
            css: n.prev_button,
            click: s
        });
        t = e("<a />", {
            href: "javascript:;",
            text: "next",
            css: n.next_button,
            click: p
        });
        r.append(o).append(a).append(t);
        e("body").append(u).append(r);
        v = function (e) {
            console.log("player ready");
            return e.target.playVideo()
        };
        h = function (e) {
            var n, t;
            n = {
                2: "invalid video id",
                5: "video not supported in html5",
                100: "video removed or private",
                101: "video not embedable",
                150: "video not embedable"
            };
            t = n[e.data] || "unknown error";
            console.log("Error", t);
            d.splice(c, 1);
            if (c >= d.length) {
                c = 0
            }
            return f()
        };
        m = function (e) {
            if (e.data === YT.PlayerState.ENDED) {
                return p()
            }
        };
        return window.onYouTubeIframeAPIReady = function () {
            return y = new YT.Player(g, {
                height: "390",
                width: "640",
                videoId: d[0],
                events: {
                    onReady: v,
                    onError: h,
                    onStateChange: m
                }
            })
        }
    };
    o = false;
    if (typeof jQuery !== "undefined" && jQuery !== null && jQuery.fn && jQuery.fn.jquery) {
        r = jQuery.fn.jquery.split(".");
        if (r.length === 3 && parseInt(r[1]) > 3) {
            console.log("using in page jquery version", jQuery.fn.jquery);
            e(jQuery);
            o = true
        }
    }
    if (!o) {
        t("node_modules/jquery/dist/jquery.min.js", function () {
            return e(jQuery.noConflict(true))
        })
    }
});

var input = document.querySelector('#playvideos');
input.addEventListener('click', function()
{
    run_playideos();
});
