// Use objects with .value to allow mutable state shared between modules

const innertubeKey = { value: null };
const clientVersion = { value: null };
const continuationToken = { value: null };
const isLoading = { value: false };

function setInnertubeKey(key) {
  innertubeKey.value = key;
}
function setClientVersion(version) {
  clientVersion.value = version;
}
function setContinuationToken(token) {
  continuationToken.value = token;
}
function setIsLoading(loading) {
  isLoading.value = loading;
}

export {
  innertubeKey,
  clientVersion,
  continuationToken,
  isLoading,
  setInnertubeKey,
  setClientVersion,
  setContinuationToken,
  setIsLoading,
};
