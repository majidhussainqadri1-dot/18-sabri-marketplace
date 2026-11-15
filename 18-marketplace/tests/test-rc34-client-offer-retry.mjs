import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
const code = readFileSync(new URL('../assets/js/marketplace.js',import.meta.url),'utf8');
const offerButton = {
  textContent: 'Submit Offer', dataset: {}, disabled: false,
  setAttribute(){}, removeAttribute(){},
};
const saveButton = {
  textContent: 'Save', dataset: {}, disabled: false,
  setAttribute(){}, removeAttribute(){},
  addEventListener(type,handler){ if(type==='click')this.click=handler; },
};
const modal = {hidden:true};
const form = {
  dataset:{}, addEventListener(type,handler){ if(type==='submit')this.submit=handler; },
  querySelector(s){return s==='button[type=submit]'?offerButton:null;},
  closest(){return modal;}, reset(){this.resetCount=(this.resetCount||0)+1;}
};
const detail = {dataset:{listingId:'00000000-0000-4000-8000-000000000001'},querySelector(s){
  if(s==='[data-mkt-save]')return saveButton;
  return null;
}};
const document = {
  body:{style:{}},activeElement:null,
  querySelector(s){
    if(s==='[data-listing-id]')return detail;
    if(s==='[data-mkt-offer-form]')return form;
    if(s==='[data-mkt-toast]')return null;
    return null;
  },querySelectorAll(){return [];},
};
let payload={amount:'500',currency:'PKR',terms:'Cash on delivery'};
let successes=[false,false,false,true,true,true];
const calls=[];let seq=0;
class FakeFormData{
  constructor(object){assert.equal(object,form);}
  entries(){return Object.entries(payload);}
}
const context=vm.createContext({
  window:{MKT_APP:{restUrl:'https://sabrihomeopathy.com/wp-json/marketplace/v1/',strings:{working:'Working',error:'Error',saved:'Saved.'}}},
  document,FormData:FakeFormData,
  crypto:{getRandomValues(arr){arr[0]=++seq;arr[1]=123;return arr;}},
  Uint32Array, URL, URLSearchParams,
  location:{origin:'https://sabrihomeopathy.com',search:''},
  fetch:async (_url,options)=>{
    calls.push({key:options.headers['Idempotency-Key'],body:JSON.parse(options.body)});
    const success=successes.shift();
    return {ok:success,json:async()=>success?{public_id:'offer-uuid'}:{message:'Simulated interrupted submission'}};
  }
});
vm.runInContext(code,context,{filename:'marketplace.js'});
assert.equal(typeof form.submit,'function','Offer submit bound');
assert.equal(typeof saveButton.click,'function','Save click bound');
const submit=()=>form.submit({preventDefault(){},currentTarget:form});
await submit(); // failed, but server may have committed
await submit(); // identical retry must use same key
assert.equal(calls[0].key,calls[1].key,'Same offer payload must reuse idempotency key after failure');
payload={...payload,amount:'550'};
await submit(); // changed terms -> new key
assert.notEqual(calls[1].key,calls[2].key,'Changed offer must receive fresh idempotency key');
await submit(); // same edited command, successful
assert.equal(calls[2].key,calls[3].key,'Edited command retains stable key on retry');
assert.equal(form.resetCount,1,'Successful request resets the form once');
await submit(); // a truly new command after success
assert.notEqual(calls[3].key,calls[4].key,'After confirmed success next offer uses new key');
assert.equal(offerButton.disabled,false,'Submit button reenabled on completed command');
await saveButton.click({currentTarget:saveButton});
assert.equal(saveButton.textContent,'♥ Saved','Save success remains visible after busy cleanup');
assert.equal(saveButton.disabled,false,'Save button is reenabled after response');
assert.equal(calls[5].body.save,true,'Saved request is properly formed');
console.log('PASS RC34: 9 offer replay, edit, success reset, and save-button assertions');
