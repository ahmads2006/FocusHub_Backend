import boto3
import json
import time
import os

# Initialize LocalStack SQS & S3 clients
endpoint_url = "http://localhost:4566"
region_name = "us-east-1"

sqs = boto3.client('sqs', endpoint_url=endpoint_url, region_name=region_name, aws_access_key_id='test', aws_secret_access_key='test')
s3 = boto3.client('s3', endpoint_url=endpoint_url, region_name=region_name, aws_access_key_id='test', aws_secret_access_key='test')

QUEUE_URL = "http://sqs.us-east-1.localhost.localstack.cloud:4566/000000000000/image-processing-queue"

def process_image(bucket, key):
    print(f"[*] Processing image: {key} from bucket: {bucket}")
    
    # 1. Download image from S3 (LocalStack)
    tmp_path = f"/tmp/{key.replace('/', '_')}"
    s3.download_file(bucket, key, tmp_path)
    print(f"    -> Downloaded to {tmp_path}")
    
    # 2. Add your AI/Image processing logic here
    time.sleep(1) # Simulating processing time
    
    # 3. Inform Laravel (e.g. via Database or API)
    print(f"    -> Finished processing {key}\n")
    
    # Cleanup tmp file
    if os.path.exists(tmp_path):
        os.remove(tmp_path)

def listen_queue():
    print(f"[*] Listening to SQS Queue: {QUEUE_URL}")
    while True:
        try:
            response = sqs.receive_message(
                QueueUrl=QUEUE_URL,
                MaxNumberOfMessages=1,
                WaitTimeSeconds=10 # Long-polling for 10 seconds
            )
            
            if 'Messages' in response:
                for msg in response['Messages']:
                    receipt_handle = msg['ReceiptHandle']
                    body = json.loads(msg['Body'])
                    
                    # Parse S3 Event Notification format
                    if 'Records' in body:
                        for record in body['Records']:
                            if record['eventName'].startswith('ObjectCreated:'):
                                bucket_name = record['s3']['bucket']['name']
                                object_key = record['s3']['object']['key']
                                process_image(bucket_name, object_key)
                    
                    # Delete the message after successful processing
                    sqs.delete_message(
                        QueueUrl=QUEUE_URL,
                        ReceiptHandle=receipt_handle
                    )
        except Exception as e:
            print(f"[!] Error: {str(e)}")
            time.sleep(5)

if __name__ == "__main__":
    listen_queue()
