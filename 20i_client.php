<?php
  namespace TwentyI\Stack;

  class MyREST
  {
      private $bearerToken;
      private function sendRequest($url, $options = [])
      {
          $original_headers = isset($options[CURLOPT_HTTPHEADER]) ?
              $options[CURLOPT_HTTPHEADER] :
              [];
          unset($options[CURLOPT_HTTPHEADER]);
          $ch = curl_init($url);
          curl_setopt_array($ch, $options + [
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_HTTPHEADER => $original_headers + [
                  "Expect:",
                  // ^Otherwise Curl will add Expect: 100 Continue, which is wrong.
                  "Authorization: Bearer " . base64_encode($this->bearerToken),
              ],
          ]);
          $response = curl_exec($ch);
          if ($response === false) {
              throw new \Exception("Curl error: " . curl_error($ch));
          }

          $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

          if (preg_match('/^404/', $status)) {
              trigger_error("404 on $url");
              $response = null;
          } elseif (preg_match('/^[45]/', $status)) {
              throw new \Exception("HTTP error {$status} on {$url}");
          }

          curl_close($ch);
          return $response;
      }
      public function __construct($bearer_token)
      {
          $this->bearerToken = $bearer_token;
      }
      public function deleteWithFields($url, $fields = [], $options = [])
      {
          if (count($fields) > 0) {
              $query = array_reduce(
                  array_keys($fields),
                  function ($carry, $item) use ($fields) {
                      return ($carry ? "$carry&" : "?") .
                          urlencode($item) . "=" . urlencode($fields[$item]);
                  },
                  ""
              );
          } else {
              $query = "";
          }

          $response = $this->sendRequest($url . $query, [
              CURLOPT_CUSTOMREQUEST => "DELETE",
          ] + $options);

          return json_decode($response);
      }
      public function getRawWithFields($url, $fields = [], $options = [])
      {
          if (count($fields) > 0) {
              $query = array_reduce(
                  array_keys($fields),
                  function ($carry, $item) use ($fields) {
                      return ($carry ? "$carry&" : "?") .
                          urlencode($item) . "=" . urlencode($fields[$item]);
                  },
                  ""
              );
          } else {
              $query = "";
          }

          return $this->sendRequest($url . $query, $options);
      }
      public function getWithFields($url, $fields = [], $options = [])
      {
          $response = $this->getRawWithFields($url, $fields, $options);
          return json_decode($response);
      }
      public function postWithFields($url, $fields, $options = [])
      {
          $original_headers = isset($options[CURLOPT_HTTPHEADER]) ?
              $options[CURLOPT_HTTPHEADER] :
              [];
          unset($options[CURLOPT_HTTPHEADER]);
          $response = $this->sendRequest($url, [
              CURLOPT_HTTPHEADER => $original_headers + [
                  "Content-Type: application/json",
              ],
              CURLOPT_POST => true,
              CURLOPT_POSTFIELDS => json_encode($fields),
          ] + $options);
          return json_decode($response);
      }
      public function putWithFields($url, $fields, $options = [])
      {
          $original_headers = isset($options[CURLOPT_HTTPHEADER]) ?
              $options[CURLOPT_HTTPHEADER] :
              [];
          unset($options[CURLOPT_HTTPHEADER]);
          $response = $this->sendRequest($url, [
              CURLOPT_HTTPHEADER => $original_headers + [
                  "Content-Length: " . strlen($fields),
              ],
              CURLOPT_CUSTOMREQUEST => "PUT",
              CURLOPT_POSTFIELDS => $fields,
          ] + $options);
          return json_decode($response);
      }
  }