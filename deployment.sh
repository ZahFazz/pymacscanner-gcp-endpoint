  gcloud functions deploy pymacscannerEndpoint \
    --gen2 \
    --runtime=php82 \
    --region=asia-southeast2 \
    --source=. \
    --entry-point=pymacscannerEndpoint \
    --trigger-http \
    --allow-unauthenticated