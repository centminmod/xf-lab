// Run in the local claims-browser page. No external widgets or actual token values.
window.claimsAdditionalProbe = async function () {
 const runs = {};
 for (const [label,port] of [['base',8872],['wmtech',8874]]) {
  const frame=document.createElement('iframe');const ready=new Promise(resolve=>frame.onload=resolve);
  frame.srcdoc='<!doctype html><html><body><script src="http://localhost:'+port+'/js/videojs/video.js"><\/script></body></html>';
  document.body.appendChild(frame);await ready;
  if(!frame.contentWindow.videojs)throw Error('video.js did not initialize');
  runs[label]=Array.from(frame.contentWindow.videojs.options.techOrder);frame.remove();
 }
 if(JSON.stringify(runs.base)!=='["html5","flash"]'||JSON.stringify(runs.wmtech)!=='["html5"]')throw Error('Unexpected native technology order');
 return {videoTechOrder:runs,encoding:{input:'a+b',legacyEscape:escape('a+b'),componentEncoding:encodeURIComponent('a+b')},scope:'Post-panel native browser configuration and built-in encoding control. No media playback, Flash runtime or live Facebook request.'};
};
