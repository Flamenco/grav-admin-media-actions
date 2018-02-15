/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2018 TwelveTone LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
// MEDIA_ACTIONS must be set
// MEDIA_ACTION_TASK_URL must be set


function _onMediaAction(actionId, mediaName, dz) {
    let fn = "onMediaAction_" + actionId;
    if (typeof window[fn] === 'function') {
        window[fn].apply(null, [actionId, mediaName, dz]);
    } else {
        submitMediaAction(actionId, mediaName, "");
    }
}

function submitMediaAction(actionId, mediaName, payload = "", callback = null, modal = null) {
    if (modal) {
        $('.loading', modal).removeClass('hidden');
        $('.button', modal).addClass('hidden');
    }
    var data = new FormData();
    data.append('admin-nonce', GravAdmin.config.admin_nonce);
    data.append("action_id", actionId);
    data.append("media_name", mediaName);
    data.append("payload", JSON.stringify(payload));
    fetch(MEDIA_ACTION_TASK_URL, {method: 'POST', body: data, credentials: 'same-origin'})
        .then(res => res.json())
        .then(result => {
            if (modal) {
                if (!result.error) {
                    modal.close();
                }
            }
            if (callback) {
                callback(result);
            }
        });
}

// Check for new media every 1000 ms and add actions
setInterval(function () {
    const size = 25; // The action icon size
    const maxRows = 5;
    const colMargin = 2;

    $('.dz-preview').each(function (i, dz) {
        if (!dz._actions) {
            dz._actions = true;
            let actionsIndex = 3; //TODO hardcoded to standard action count
            //let top = 72; //TODO get max top of children (they are not in order)
            //let top = actionsCount * size - size; // the standard icons ar off by 1 pixel!?

            const that = this;
            MEDIA_ACTIONS.forEach(function (item) {
                actionsIndex++;
                let faIcon = item.icon;
                if (!faIcon) {
                    faIcon = "fa-play-circle";
                }
                if (!faIcon.startsWith('fa-')) {
                    faIcon = 'fa-' + faIcon;
                }
                const ele = document.createElement('a');
                ele.className = 'dz-media-action';
                ele.style.top = (Math.floor(actionsIndex % maxRows) * size - (Math.floor(actionsIndex % maxRows)) - 1) + 'px';
                let right;
                const col = Math.floor(actionsIndex / maxRows);
                if (col === 0) {
                    right = -size;
                } else {
                    right = -((1 + Math.floor((actionsIndex) / maxRows)) * size) - (col * colMargin);
                }
                ele.style.right = right + 'px';
                ele.href = 'javascript:undefined;';
                ele.title = item.caption;
                ele.innerText = "";//item.caption;
                const nameEle = $(dz).find('[data-dz-name]');
                ele._file_name = nameEle.text();
                ele._dz_preview = dz;
                $(that).append(ele);
                const i = document.createElement("i");
                ele.appendChild(i);
                i.className = 'fa fa-fw ' + faIcon;
                ele.addEventListener('click', () => _onMediaAction(item.actionId, nameEle.text(), dz));

                //Invisible div to maintain hover when the mouseover is on the column margin
                const ele2 = document.createElement('div');
                ele2.className = 'dz-media-action';
                ele2.style.background = 'transparent';
                ele2.style.right = (right + colMargin) + "px";
                ele2.style.top = ele.style.top;
                $(ele2).insertBefore(ele);
            });
            dz.style.marginRight = 15 + Math.floor(actionsIndex / maxRows) * (size + colMargin) + "px";
        }
    });
}, 1000);

